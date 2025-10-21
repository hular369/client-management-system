# Client Management System

This is a robust, production-ready Laravel + React application for managing client data with advanced CSV import/export functionality, intelligent duplicate detection, and efficient batch processing.

## 🚀 Features

### Core Functionality
- **📤 CSV Import**: Upload and process client data from CSV files with validation
- **🔍 Duplicate Detection**: Automatic detection of duplicate records based on company name, email, and phone number
- **👥 Client Management**: View, search, filter, and manage client records
- **📥 CSV Export**: Export filtered data in CSV format (all, unique, or duplicates only)
- **⚡ Batch Processing**: Handle large CSV files efficiently using Laravel Queues
- **📊 Real-time Progress**: Track import progress with live progress bars

### Advanced Features
- **Background Processing**: Large file imports processed asynchronously
- **Progress Tracking**: Real-time progress updates during imports
- **Comprehensive Validation**: Robust error handling and validation reporting
- **Responsive UI**: Modern React interface with Tailwind CSS styling
- **RESTful API**: Well-structured API endpoints for all operations
- **Duplicate Management**: Tools to view and resolve duplicate records
- **Import Session Management**: Track and cancel ongoing imports

## 🛠️ Technology Stack

### Backend
- **Laravel 12.x** - PHP framework
- **MySQL** - Database
- **Laravel Queues** - Background job processing
- **League CSV** - CSV file processing
- **PHPUnit** - Testing framework

### Frontend
- **React 18** - UI library
- **Axios** - HTTP client
- **Custom CSS** - Styling (Tailwind-inspired)
- **Vite** - Build tool

### Architecture
- **Service Classes** - Business logic separation
- **Repository Pattern** - Data access abstraction
- **Dependency Injection** - Loose coupling
- **Queue Workers** - Asynchronous processing

## 📋 Prerequisites

### Backend Requirements
- PHP 8.2 or higher
- Composer 2.0+
- MySQL 5.7+ 
- Laravel 12.x compatible environment

### Frontend Requirements
- Node.js 18.0+
- npm 9.0+ 

### Optional (Production)
- Supervisor (for managing queue workers)
- Nginx/Apache web server

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone <repository-url>
cd client_management_system