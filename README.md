# Client Management System

This is a production-ready Laravel + React application for managing client data with advanced CSV import/export functionality, intelligent duplicate detection, and efficient batch processing of large date files.

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

### 1. Clone the Repository and install dependencies
```bash
git clone https://github.com/hular369/client-management-system.git
cd client_management_system

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build

Update the .env file with specific Database and Queue configurations:
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=client_management
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Queue Configuration (for production)
QUEUE_CONNECTION=database


### 2. Run migrtions:

# Create database tables
php artisan migrate

# Run seeders for sample data
php artisan db:seed


### 3. Optionally, you can also create sample data:

# Seed clients with duplicates for testing
php artisan db:seed --class=ClientSeeder

# Seed import sessions with various statuses
php artisan db:seed --class=ImportSessionSeeder

# Copy sample CSV files to storage
php artisan db:seed --class=SampleFilesSeeder


🧪 Running Tests:

# Run all tests:
php artisan test

# Run specific test file:
php artisan test tests/Feature/ClientImportTest.php

# Run with coverage:
php artisan test --coverage


### 4. Run the application for test/dev mode:

# Terminal 1 - Start Laravel development server
php artisan serve

# Terminal 2 - Start frontend development server (optional)
npm run dev

# Terminal 3 - Start queue worker for background processing
php artisan queue:work

bash```

Finally browse at http://localhost:8000