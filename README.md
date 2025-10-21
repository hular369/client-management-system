# 🧾 Client Management System

A **production-ready Laravel + React** application for managing client data with **intelligent CSV import/export**, **duplicate detection**, and **efficient batch processing** for large datasets.

---

## 🚀 Features

### 🔧 Core Functionality
- 📤 **CSV Import** – Upload and process client data from CSV files with validation  
- 🔍 **Duplicate Detection** – Automatically detect duplicate records (by company, email, phone)  
- 👥 **Client Management** – View, search, filter, and manage client records  
- 📥 **CSV Export** – Export filtered data (all, unique, or duplicates only)  
- ⚡ **Batch Processing** – Handle large CSV files efficiently using Laravel Queues  
- 📊 **Real-time Progress** – Track import progress with live updates  

### 🌟 Advanced Features
- 🧵 **Background Processing** – Large imports handled asynchronously  
- ⏱️ **Progress Tracking** – Real-time updates during imports  
- 🧩 **Comprehensive Validation** – Detailed validation and error reporting  
- 💻 **Responsive UI** – Modern React + Tailwind-inspired interface  
- 🧠 **RESTful API** – Clean and modular endpoints for all operations  
- 🔄 **Duplicate Management** – View and resolve duplicate records easily  
- 🧮 **Import Session Management** – Track, resume, or cancel ongoing imports  

---

## 🛠️ Technology Stack

### Backend
- **Laravel 12.x** – PHP Framework  
- **MySQL** – Relational Database  
- **Laravel Queues** – Background job processing  
- **League CSV** – Efficient CSV parsing  
- **PHPUnit** – Unit and feature testing  

### Frontend
- **React 18** – Modern UI library  
- **Axios** – API communication  
- **Tailwind-inspired Custom CSS** – Clean styling  
- **Vite** – Lightning-fast build tool  

### Architecture
- 🧱 **Service Classes** – Clear business logic separation  
- 💉 **Dependency Injection** – Promotes loose coupling  
- ⚙️ **Queue Workers** – For asynchronous imports  

---

## 📋 Prerequisites

### Backend Requirements
- PHP **8.2+**  
- Composer **2.0+**  
- MySQL **5.7+**  
- Laravel **12.x** compatible environment  

### Frontend Requirements
- Node.js **18.0+**  
- npm **9.0+**

### Optional (Production)
- **Supervisor** (for managing queue workers)  
- **Nginx/Apache** web server  

---

## ⚙️ Installation

### 1️⃣ Clone the Repository & Install Dependencies

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


2️⃣ Configure Environment

Update your .env file with database and queue settings:
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=client_management
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Queue Configuration (for production)
QUEUE_CONNECTION=database

3️⃣ Run Migrations & Seeders
# Create database tables
php artisan migrate

# Seed basic sample data
php artisan db:seed

Optional seeders for testing duplicates and import sessions:
php artisan db:seed --class=ClientSeeder
php artisan db:seed --class=ImportSessionSeeder
php artisan db:seed --class=SampleFilesSeeder


🧪 Running Tests:

# Run all tests
php artisan test

# Run specific test
php artisan test tests/Feature/ClientImportTest.php

# Run with coverage
php artisan test --coverage


💻 Development Usage

Run the following commands in separate terminals:
# Terminal 1 - Start Laravel server
php artisan serve

# Terminal 2 - Start frontend development server
npm run dev

# Terminal 3 - Start queue worker for background jobs
php artisan queue:work

Access the application at:
👉 http://localhost:8000