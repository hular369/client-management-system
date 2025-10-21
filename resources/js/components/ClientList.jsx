import React, { useState, useEffect } from 'react';
import axios from 'axios';

const ClientList = () => {
    const [clients, setClients] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [filter, setFilter] = useState('all');
    const [currentPage, setCurrentPage] = useState(1);
    const [perPage, setPerPage] = useState(15);

    useEffect(() => {
        fetchClients();
    }, [currentPage, perPage, filter]);

    const fetchClients = async () => {
        try {
            setLoading(true);
            const response = await axios.get('/api/clients', {
                params: {
                    page: currentPage,
                    per_page: perPage,
                    filter: filter !== 'all' ? filter : undefined,
                },
            });
            setClients(response.data);
        } catch (error) {
            console.error('Error fetching clients:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleExport = async (exportFilter = 'all') => {
        try {
            const response = await axios.get('/api/clients/export', {
                params: { filter: exportFilter },
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

    const handleDelete = async (clientId) => {
        if (!window.confirm('Are you sure you want to delete this client?')) {
            return;
        }

        try {
            await axios.delete(`/api/clients/${clientId}`);
            fetchClients();
        } catch (error) {
            console.error('Delete error:', error);
            alert('Failed to delete client');
        }
    };

    const filteredClients = clients.data?.filter(client =>
        client.company_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        client.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
        client.phone_number.includes(searchTerm)
    ) || [];

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
            {/* Header with controls */}
            <div className="card-header">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                    <h2 className="text-lg font-semibold text-gray-900">Client List</h2>
                    
                    <div className="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                        <button
                            onClick={() => handleExport('all')}
                            className="btn btn-secondary"
                        >
                            Export All
                        </button>
                        <button
                            onClick={() => handleExport('unique')}
                            className="btn btn-secondary"
                        >
                            Export Unique
                        </button>
                        <button
                            onClick={() => handleExport('duplicates')}
                            className="btn btn-secondary"
                        >
                            Export Duplicates
                        </button>
                    </div>
                </div>

                {/* Search and Filter */}
                <div className="mt-4 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <div className="flex-1">
                        <div className="search-container">
                            <div className="search-icon">🔍</div>
                            <input
                                type="text"
                                placeholder="Search clients..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="search-input"
                            />
                        </div>
                    </div>
                    
                    <div className="flex space-x-2">
                        <select
                            value={filter}
                            onChange={(e) => setFilter(e.target.value)}
                            className="form-select"
                        >
                            <option value="all">All Clients</option>
                            <option value="unique">Unique Only</option>
                            <option value="duplicates">Duplicates Only</option>
                        </select>
                        
                        <select
                            value={perPage}
                            onChange={(e) => setPerPage(Number(e.target.value))}
                            className="form-select"
                        >
                            <option value="10">10 per page</option>
                            <option value="15">15 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Client Table */}
            <div className="table-container">
                <table className="table">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filteredClients.length === 0 ? (
                            <tr>
                                <td colSpan="5" className="text-center">
                                    <div className="empty-state">
                                        {searchTerm ? 'No clients match your search' : 'No clients found'}
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            filteredClients.map((client) => (
                                <tr key={client.id}>
                                    <td className="font-medium text-gray-900">
                                        {client.company_name}
                                    </td>
                                    <td>
                                        {client.email}
                                    </td>
                                    <td>
                                        {client.phone_number}
                                    </td>
                                    <td>
                                        {client.is_duplicate ? (
                                            <span className="badge badge-warning">
                                                Duplicate
                                            </span>
                                        ) : (
                                            <span className="badge badge-success">
                                                Unique
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        <button
                                            onClick={() => handleDelete(client.id)}
                                            className="btn btn-danger btn-sm"
                                            title="Delete client"
                                        >
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {clients.meta && filteredClients.length > 0 && (
                <div className="pagination">
                    <div className="pagination-info">
                        Showing {clients.meta.from} to {clients.meta.to} of {clients.meta.total} results
                    </div>
                    <div className="flex">
                        <button
                            onClick={() => setCurrentPage(currentPage - 1)}
                            disabled={currentPage === 1}
                            className="pagination-button"
                        >
                            Previous
                        </button>
                        <button
                            onClick={() => setCurrentPage(currentPage + 1)}
                            disabled={currentPage === clients.meta.last_page}
                            className="pagination-button"
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default ClientList;