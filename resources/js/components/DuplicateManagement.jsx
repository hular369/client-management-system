import React, { useState, useEffect } from 'react';
import axios from 'axios';

const DuplicateManagement = () => {
    const [duplicateGroups, setDuplicateGroups] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedGroup, setSelectedGroup] = useState(null);

    useEffect(() => {
        fetchDuplicateGroups();
    }, []);

    const fetchDuplicateGroups = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/api/clients/duplicate-groups');
            setDuplicateGroups(response.data.data);
        } catch (error) {
            console.error('Error fetching duplicate groups:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleExportGroup = async (groupHash) => {
        try {
            const response = await axios.get('/api/clients/export', {
                params: { duplicate_group: groupHash },
            });
            
            const { filename, content } = response.data.data;
            const blob = new Blob([atob(content)], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Export error:', error);
            alert('Export failed: ' + (error.response?.data?.message || error.message));
        }
    };

    const handleDeleteClient = async (clientId) => {
        if (!window.confirm('Are you sure you want to delete this client?')) {
            return;
        }

        try {
            await axios.delete(`/api/clients/${clientId}`);
            if (selectedGroup) {
                const group = duplicateGroups.find(g => g.hash === selectedGroup.hash);
                if (group.clients.length === 2) {
                    fetchDuplicateGroups();
                    setSelectedGroup(null);
                } else {
                    fetchDuplicateGroups();
                }
            } else {
                fetchDuplicateGroups();
            }
        } catch (error) {
            console.error('Delete error:', error);
            alert('Failed to delete client');
        }
    };

    const handleKeepOneDeleteOthers = async (groupHash, keepClientId) => {
        if (!window.confirm('Are you sure you want to keep this one and delete all other duplicates in this group?')) {
            return;
        }

        try {
            const group = duplicateGroups.find(g => g.hash === groupHash);
            const clientsToDelete = group.clients.filter(client => client.id !== keepClientId);
            
            for (const client of clientsToDelete) {
                await axios.delete(`/api/clients/${client.id}`);
            }
            
            fetchDuplicateGroups();
            setSelectedGroup(null);
        } catch (error) {
            console.error('Error cleaning duplicates:', error);
            alert('Failed to clean duplicates');
        }
    };

    if (loading) {
        return (
            <div className="card">
                <div className="loading-spinner">
                    <div className="spinner"></div>
                </div>
            </div>
        );
    }

    return (
        <div className="card">
            {/* Header */}
            <div className="card-header">
                <div className="flex items-center justify-between">
                    <div className="flex items-center">
                        <div className="text-yellow-500 mr-3">⚠️</div>
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900">Duplicate Management</h2>
                            <p className="text-sm text-gray-500">
                                Manage and resolve duplicate client records
                            </p>
                        </div>
                    </div>
                    <div className="text-sm text-gray-500">
                        {duplicateGroups.length} duplicate groups found
                    </div>
                </div>
            </div>

            <div className="duplicate-layout">
                {/* Duplicate Groups List - Sidebar */}
                <div className="duplicate-sidebar">
                    <div className="p-4">
                        <h3 className="text-sm font-medium text-gray-700 mb-3">Duplicate Groups</h3>
                        {duplicateGroups.length === 0 ? (
                            <div className="empty-state">
                                <div className="empty-icon">👥</div>
                                <p className="empty-text">No duplicate groups found</p>
                            </div>
                        ) : (
                            <div className="duplicate-group-list">
                                {duplicateGroups.map((group) => (
                                    <div
                                        key={group.hash}
                                        onClick={() => setSelectedGroup(group)}
                                        className={`duplicate-group-item ${selectedGroup?.hash === group.hash ? 'selected' : ''}`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center flex-1">
                                                <div className="text-yellow-500 mr-2">⚠️</div>
                                                <span className="text-sm font-medium text-gray-900">
                                                    {group.clients[0].company_name}
                                                </span>
                                            </div>
                                            <span className="badge badge-warning">
                                                {group.count} duplicates
                                            </span>
                                        </div>
                                        <div className="text-xs text-gray-500 truncate mt-1">
                                            {group.clients[0].email}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Selected Group Details - Main Content */}
                <div className="duplicate-content p-6">
                    {selectedGroup ? (
                        <>
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900">
                                        {selectedGroup.clients[0].company_name}
                                    </h3>
                                    <p className="text-sm text-gray-500">
                                        {selectedGroup.count} duplicate records found
                                    </p>
                                </div>
                                <button
                                    onClick={() => handleExportGroup(selectedGroup.hash)}
                                    className="btn btn-secondary"
                                >
                                    📥 Export Group
                                </button>
                            </div>

                            <div className="space-y-4">
                                {selectedGroup.clients.map((client, index) => (
                                    <div
                                        key={client.id}
                                        className="client-card"
                                    >
                                        <div className="flex items-center justify-between mb-3">
                                            <div className="flex items-center flex-1">
                                                <span className="badge badge-info mr-2">
                                                    #{index + 1}
                                                </span>
                                                <h4 className="text-sm font-medium text-gray-900">
                                                    {client.company_name}
                                                </h4>
                                            </div>
                                            <div className="flex space-x-2 ml-4">
                                                <button
                                                    onClick={() => handleKeepOneDeleteOthers(selectedGroup.hash, client.id)}
                                                    className="btn btn-success btn-sm"
                                                    title="Keep this one and delete others"
                                                >
                                                    Keep This
                                                </button>
                                                <button
                                                    onClick={() => handleDeleteClient(client.id)}
                                                    className="btn btn-danger btn-sm"
                                                    title="Delete this client"
                                                >
                                                    🗑️
                                                </button>
                                            </div>
                                        </div>
                                        <div className="client-details">
                                            <div className="detail-group">
                                                <span className="detail-label">Email:</span>
                                                <span className="detail-value">{client.email}</span>
                                            </div>
                                            <div className="detail-group">
                                                <span className="detail-label">Phone:</span>
                                                <span className="detail-value">{client.phone_number}</span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Resolution Suggestions */}
                            <div className="alert alert-warning mt-6">
                                <h4 className="font-medium">
                                    Resolution Suggestions
                                </h4>
                                <ul className="list-disc pl-5 mt-2 space-y-1">
                                    <li>Click "Keep This" on the record you want to preserve, and delete all others</li>
                                    <li>Manually review each duplicate before deletion</li>
                                    <li>Export the group for external review if needed</li>
                                </ul>
                            </div>
                        </>
                    ) : (
                        <div className="empty-state h-64">
                            <div className="empty-icon">📋</div>
                            <p className="empty-text">
                                {duplicateGroups.length > 0 
                                    ? "Select a duplicate group to view details"
                                    : "No duplicate groups to manage"
                                }
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default DuplicateManagement;