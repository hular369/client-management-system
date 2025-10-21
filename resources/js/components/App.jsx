import React, { useState } from 'react';
import ClientImport from './ClientImport';
import ClientList from './ClientList';
import DuplicateManagement from './DuplicateManagement';

function App() {
    const [activeTab, setActiveTab] = useState('import');

    const tabs = [
        { id: 'import', label: 'Import CSV' },
        { id: 'clients', label: 'Client List' },
        { id: 'duplicates', label: 'Duplicates' },
    ];

    return (
        <div className="app-container">
            {/* Header */}
            <header className="app-header">
                <div className="container">
                    <div className="flex justify-between items-center h-16">
                        <div className="flex items-center">
                            <div className="w-8 h-8 bg-blue-600 rounded-md mr-3 flex items-center justify-center text-white font-bold">
                                C
                            </div>
                            <h1 className="text-xl font-bold text-gray-900">
                                Client Management System
                            </h1>
                        </div>
                    </div>
                </div>
            </header>

            {/* Navigation */}
            <nav className="app-nav">
                <div className="container">
                    <div className="flex space-x-8">
                        {tabs.map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`nav-button ${activeTab === tab.id ? 'active' : 'inactive'}`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </div>
            </nav>

            {/* Main Content */}
            <main className="app-main">
                <div className="px-4 py-6 sm:px-0">
                    {activeTab === 'import' && <ClientImport />}
                    {activeTab === 'clients' && <ClientList />}
                    {activeTab === 'duplicates' && <DuplicateManagement />}
                </div>
            </main>
        </div>
    );
}

export default App;