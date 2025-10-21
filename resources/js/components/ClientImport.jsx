import React, { useState } from 'react';
import axios from 'axios';

const ClientImport = () => {
    const [file, setFile] = useState(null);
    const [isUploading, setIsUploading] = useState(false);
    const [importResult, setImportResult] = useState(null);
    const [errors, setErrors] = useState([]);
    const [importSession, setImportSession] = useState(null);
    const [progress, setProgress] = useState(null);

    const handleFileChange = (e) => {
        const selectedFile = e.target.files[0];
        setFile(selectedFile);
        setImportResult(null);
        setErrors([]);
        setImportSession(null);
        setProgress(null);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (!file) {
            setErrors(['Please select a CSV file to upload.']);
            return;
        }

        setIsUploading(true);
        setErrors([]);

        const formData = new FormData();
        formData.append('csv_file', file);

        try {
            const response = await axios.post('/api/clients/import', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            if (response.data.success) {
                setImportSession(response.data.data);
                setImportResult({
                    success: true,
                    message: 'Import started! Processing your file in the background...'
                });
                
                // Start polling for progress
                pollImportProgress(response.data.data.session_id);
                
                setFile(null);
                document.getElementById('csv-file').value = '';
            } else {
                setImportResult(response.data);
            }
        } catch (error) {
            console.error('Import error:', error);
            setErrors([error.response?.data?.message || 'An error occurred during import.']);
        } finally {
            setIsUploading(false);
        }
    };

    const pollImportProgress = async (sessionId) => {
        const checkProgress = async () => {
            try {
                const response = await axios.get('/api/clients/import-progress', {
                    params: { session_id: sessionId }
                });

                if (response.data.success) {
                    setProgress(response.data.data);

                    // Continue polling if still processing
                    if (response.data.data.status === 'processing') {
                        setTimeout(checkProgress, 2000); // Check every 2 seconds
                    } else if (response.data.data.status === 'completed') {
                        setImportResult({
                            success: true,
                            message: `Import completed! Processed ${response.data.data.total_rows} rows.`,
                            data: {
                                imported_count: response.data.data.imported_count,
                                duplicate_count: response.data.data.duplicate_count,
                                error_count: response.data.data.error_count,
                            }
                        });
                    } else if (response.data.data.status === 'failed') {
                        setImportResult({
                            success: false,
                            message: 'Import failed. Please try again.',
                        });
                    }
                }
            } catch (error) {
                console.error('Progress check error:', error);
            }
        };

        checkProgress();
    };

    const handleCancelImport = async () => {
        if (!importSession) return;

        try {
            await axios.post('/api/clients/cancel-import', {
                session_id: importSession.session_id
            });
            setImportResult({
                success: false,
                message: 'Import cancelled.'
            });
            setProgress(null);
            setImportSession(null);
        } catch (error) {
            console.error('Cancel error:', error);
        }
    };

    const downloadTemplate = () => {
        const template = "company_name,email,phone_number\nExample Corp,contact@example.com,1234567890\nTest Company,info@test.com,9876543210";
        const blob = new Blob([template], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'client_import_template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    };

    return (
        <div className="card">
            <div className="card-header">
                <h2 className="text-lg font-semibold text-gray-900">Import Clients from CSV</h2>
            </div>
            
            <div className="card-content">
                {/* Download Template */}
                <div className="alert alert-info mb-6">
                    <h3 className="font-medium text-blue-800 mb-2">Need a template?</h3>
                    <button
                        onClick={downloadTemplate}
                        className="btn btn-secondary"
                    >
                        Download CSV Template
                    </button>
                </div>

                {/* Upload Form */}
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="form-group">
                        <label className="form-label">
                            Select CSV File
                        </label>
                        <input
                            id="csv-file"
                            type="file"
                            accept=".csv,.txt"
                            onChange={handleFileChange}
                            className="form-input"
                        />
                        <p className="form-help">
                            CSV file with columns: company_name, email, phone_number
                        </p>
                        <p className="form-help text-xs">
                            Large files will be processed in the background. You can close this page and check back later.
                        </p>
                    </div>

                    {errors.length > 0 && (
                        <div className="alert alert-error">
                            <h3 className="font-medium">
                                There were errors with your submission
                            </h3>
                            <ul className="list-disc pl-5 mt-2 space-y-1">
                                {errors.map((error, index) => (
                                    <li key={index}>{error}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Progress Display */}
                    {progress && progress.status === 'processing' && (
                        <div className="alert alert-info">
                            <div className="flex items-center justify-between mb-2">
                                <h3 className="font-medium">Processing your file...</h3>
                                <button
                                    onClick={handleCancelImport}
                                    className="btn btn-danger btn-sm"
                                >
                                    Cancel Import
                                </button>
                            </div>
                            <div className="w-full bg-gray-200 rounded-full h-2.5">
                                <div 
                                    className="bg-blue-600 h-2.5 rounded-full transition-all duration-300"
                                    style={{ width: `${progress.progress}%` }}
                                ></div>
                            </div>
                            <div className="flex justify-between text-sm mt-2">
                                <span>{progress.progress}% complete</span>
                                <span>{progress.processed_rows} of {progress.total_rows} rows processed</span>
                            </div>
                            <div className="text-sm mt-2">
                                <p>Imported: {progress.imported_count} clients</p>
                                <p>Duplicates found: {progress.duplicate_count}</p>
                                <p>Errors: {progress.error_count}</p>
                            </div>
                        </div>
                    )}

                    {importResult && !progress && (
                        <div className={`alert ${importResult.success ? 'alert-success' : 'alert-warning'}`}>
                            <h3 className="font-medium">
                                {importResult.message}
                            </h3>
                            {importResult.data && (
                                <div className="mt-2 text-sm">
                                    <p>Imported: {importResult.data.imported_count} clients</p>
                                    <p>Duplicates found: {importResult.data.duplicate_count}</p>
                                    <p>Errors: {importResult.data.error_count}</p>
                                    
                                    {importResult.data.errors && importResult.data.errors.length > 0 && (
                                        <div className="mt-2">
                                            <h4 className="font-medium">Detailed Errors:</h4>
                                            <ul className="list-disc pl-5 mt-1">
                                                {importResult.data.errors.map((error, index) => (
                                                    <li key={index}>{error}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={isUploading || !file || (progress && progress.status === 'processing')}
                        className="btn btn-primary"
                    >
                        {isUploading ? (
                            <>
                                <div className="spinner mr-2"></div>
                                Starting Import...
                            </>
                        ) : (
                            'Import CSV'
                        )}
                    </button>
                </form>
            </div>
        </div>
    );
};

export default ClientImport;