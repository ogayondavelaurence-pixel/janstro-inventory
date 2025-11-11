# Janstro Prime Inventory Management System API

**ISO/IEC 25010:2023 Compliant Web-Based Inventory Management System**

Version: 1.0.0  
Date: November 2025  
Developed by: Group 89 (BSIT - City College of Calamba)

---

## 📋 Table of Contents

1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Installation](#installation)
4. [API Documentation](#api-documentation)
5. [Testing](#testing)
6. [Security](#security)
7. [ISO/IEC 25010 Compliance](#isoiec-25010-compliance)

---

## 🎯 System Overview

### Purpose
This system replaces Janstro Prime's manual Excel-based inventory process with a secure, automated web-based solution that provides:

- **Real-time inventory tracking**
- **Automated stock monitoring**
- **Purchase order management**
- **Role-based access control**
- **Comprehensive audit logging**

### Key Features
✅ JWT-based authentication  
✅ Role-based permissions (Admin, Staff, Superadmin)  
✅ Real-time stock updates  
✅ Low stock alerts  
✅ Purchase order workflow  
✅ Transaction history  
✅ RESTful API architecture  

---

## 🏗️ Architecture

### Clean Architecture Layers

```
├── src/
│   ├── Controllers/     # HTTP request handlers
│   ├── Services/        # Business logic
│   ├── Repositories/    # Data access layer
│   ├── Models/          # Entity models
│   ├── Middleware/      # Request interceptors
│   ├── Validators/      # Input validation
│   └── Utils/           # Helpers (JWT, Response)
│
├── config/              # Configuration files
├── tests/               # TDD test suites
└── public/              # API entry point
```

### Technology Stack
- **Backend**: PHP 8.2
- **Database**: MySQL 8.0
- **Authentication**: JWT (firebase/php-jwt)
- **Environment**: vlucas/phpdotenv
- **Testing**: PHPUnit 11.5

---

## 🚀 Installation

### 1. Prerequisites
- PHP 8.2+
- MySQL 8.0+
- Composer 2.8+
- XAMPP/WAMP (local development)

### 2. Clone & Setup
```bash
cd C:\xampp\htdocs\janstro-inventory

# Install dependencies
composer install

# Copy environment file
cp .env.example .env
```

### 3. Configure Environment
Edit `.env` file:
```env
APP_NAME="Janstro Inventory System"
APP_ENV=development
APP_DEBUG=true

DB_HOST=localhost
DB_DATABASE=janstro_inventory
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=your_secure_secret_key_here
JWT_EXPIRATION=3600
```

### 4. Database Setup
```bash
# Import database schema
mysql -u root -p janstro_inventory < database/schema.sql
```

### 5. Test API
```bash
# Start XAMPP
# Open browser: http://localhost/janstro-inventory/public/health
```

**Expected Response:**
```json
{
  "success": true,
  "message": "API is running",
  "data": {
    "name": "Janstro Inventory System",
    "version": "1.0.0",
    "status": "running"
  }
}
```

---

## 📡 API Documentation

### Base URL
```
http://localhost/janstro-inventory/public
```

### Authentication
All protected endpoints require JWT token in header:
```http
Authorization: Bearer YOUR_JWT_TOKEN
```

---

### 🔐 Authentication Endpoints

#### 1. Login
```http
POST /auth/login
```

**Request:**
```json
{
  "username": "admin",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "user_id": 1,
      "username": "admin",
      "role": "admin"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600
  }
}
```

#### 2. Get Current User
```http
GET /auth/me
Authorization: Bearer YOUR_TOKEN
```

#### 3. Change Password
```http
POST /auth/change-password
Authorization: Bearer YOUR_TOKEN
```

**Request:**
```json
{
  "current_password": "oldpass",
  "new_password": "newpass123"
}
```

#### 4. Logout
```http
POST /auth/logout
Authorization: Bearer YOUR_TOKEN
```

---

### 📦 Inventory Endpoints

#### 1. Get All Items
```http
GET /inventory
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "item_id": 1,
      "item_name": "Solar Panel 250W",
      "quantity": 50,
      "unit_price": 5000.00,
      "is_low_stock": false
    }
  ]
}
```

#### 2. Get Single Item
```http
GET /inventory/{id}
Authorization: Bearer YOUR_TOKEN
```

#### 3. Get Low Stock Items
```http
GET /inventory/low-stock
Authorization: Bearer YOUR_TOKEN
```

#### 4. Create Item (Admin Only)
```http
POST /inventory
Authorization: Bearer YOUR_TOKEN
```

**Request:**
```json
{
  "item_name": "Inverter 5kW",
  "category_id": 2,
  "quantity": 10,
  "unit": "pcs",
  "reorder_level": 5,
  "unit_price": 15000.00
}
```

#### 5. Update Item (Admin Only)
```http
PUT /inventory/{id}
Authorization: Bearer YOUR_TOKEN
```

#### 6. Delete Item (Admin Only)
```http
DELETE /inventory/{id}
Authorization: Bearer YOUR_TOKEN
```

#### 7. Stock IN Operation
```http
POST /inventory/stock-in
Authorization: Bearer YOUR_TOKEN
```

**Request:**
```json
{
  "item_id": 1,
  "quantity": 20,
  "notes": "Supplier delivery"
}
```

#### 8. Stock OUT Operation
```http
POST /inventory/stock-out
Authorization: Bearer YOUR_TOKEN
```

**Request:**
```json
{
  "item_id": 1,
  "quantity": 5,
  "notes": "Installation project"
}
```

#### 9. Get Transaction History
```http
GET /inventory/transactions?limit=50
Authorization: Bearer YOUR_TOKEN
```

#### 10. Get Categories
```http
GET /inventory/categories
Authorization: Bearer YOUR_TOKEN
```

#### 11. Get Inventory Summary
```http
GET /inventory/summary
Authorization: Bearer YOUR_TOKEN
```

---

### 🛒 Purchase Order Endpoints

#### 1. Get All Orders
```http
GET /orders
Authorization: Bearer YOUR_TOKEN
```

**Optional Query:** `?status=pending`

#### 2. Get Pending Orders
```http
GET /orders/pending
Authorization: Bearer YOUR_TOKEN
```

#### 3. Create Purchase Order (Admin Only)
```http
POST /orders
Authorization: Bearer YOUR_TOKEN
```

**Request:**
```json
{
  "supplier_id": 1,
  "item_id": 1,
  "quantity": 50,
  "total_amount": 250000.00
}
```

#### 4. Update Order Status (Admin Only)
```http
PUT /orders/{id}/status
Authorization: Bearer YOUR_TOKEN
```

**Request:**
```json
{
  "status": "delivered"
}
```

**Valid statuses:** `pending`, `approved`, `delivered`, `cancelled`

#### 5. Get Order Statistics
```http
GET /orders/statistics
Authorization: Bearer YOUR_TOKEN
```

---

### 👥 User Management Endpoints (Admin Only)

#### 1. Get All Users
```http
GET /users
Authorization: Bearer YOUR_TOKEN
```

#### 2. Create User
```http
POST /users
Authorization: Bearer YOUR_TOKEN
```

**Request:**
```json
{
  "username": "staff1",
  "password": "password123",
  "name": "John Doe",
  "role_id": 2,
  "contact_no": "09171234567"
}
```

#### 3. Update User
```http
PUT /users/{id}
Authorization: Bearer YOUR_TOKEN
```

#### 4. Deactivate User
```http
PUT /users/{id}/deactivate
Authorization: Bearer YOUR_TOKEN
```

#### 5. Activate User
```http
PUT /users/{id}/activate
Authorization: Bearer YOUR_TOKEN
```

#### 6. Delete User
```http
DELETE /users/{id}
Authorization: Bearer YOUR_TOKEN
```

#### 7. Get All Roles
```http
GET /users/roles
Authorization: Bearer YOUR_TOKEN
```

---

## 🧪 Testing

### Using Postman

1. **Import Postman Collection** (create one with above endpoints)
2. **Set Environment Variables:**
   - `base_url`: `http://localhost/janstro-inventory/public`
   - `token`: (will be auto-set after login)

3. **Test Flow:**
   ```
   Login → Get Token → Test Protected Endpoints
   ```

### Manual Testing Example

```bash
# 1. Login
curl -X POST http://localhost/janstro-inventory/public/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# 2. Get Inventory (replace YOUR_TOKEN)
curl -X GET http://localhost/janstro-inventory/public/inventory \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🔒 Security

### Implemented Security Features

✅ **JWT Authentication**
- Token-based stateless authentication
- 1-hour token expiration
- Secure secret key

✅ **Role-Based Access Control (RBAC)**
- Admin: Full access
- Staff: Limited access
- Superadmin: System-wide control

✅ **Password Security**
- SHA-256 hashing
- Minimum 8 characters
- No plain text storage

✅ **SQL Injection Prevention**
- PDO prepared statements
- Parameter binding

✅ **CORS Configuration**
- Controlled cross-origin access
- Preflight handling

✅ **Audit Logging**
- All actions logged
- User accountability

---

## ✅ ISO/IEC 25010 Compliance

### 1. Performance Efficiency ⚡
- **Optimized queries** with indexes
- **Minimal overhead** routing
- **Efficient data access** patterns

### 2. Functional Suitability ✔️
- **Complete CRUD** operations
- **Business logic** validation
- **Error handling**

### 3. Security 🔐
- **JWT authentication**
- **RBAC implementation**
- **Audit trail**

### 4. Usability 👥
- **RESTful API** design
- **Clear error messages**
- **Consistent response format**

### 5. Maintainability 🛠️
- **Clean architecture**
- **PSR-4 autoloading**
- **Documented code**

---

## 📊 Project Structure

```
janstro-inventory/
│
├── config/
│   └── database.php
│
├── src/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── InventoryController.php
│   │   ├── OrderController.php
│   │   └── UserController.php
│   │
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── InventoryService.php
│   │   ├── OrderService.php
│   │   └── UserService.php
│   │
│   ├── Repositories/
│   │   ├── UserRepository.php
│   │   ├── InventoryRepository.php
│   │   ├── PurchaseOrderRepository.php
│   │   └── SupplierRepository.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Item.php
│   │   ├── PurchaseOrder.php
│   │   └── Supplier.php
│   │
│   ├── Middleware/
│   │   └── AuthMiddleware.php
│   │
│   └── Utils/
│       ├── JWT.php
│       └── Response.php
│
├── public/
│   └── index.php (API Router)
│
├── vendor/
├── .env
├── .env.example
├── composer.json
└── README.md
```

---

## 👨‍💻 Development Team

**Group 89 - BSIT Students**
- Javier, Ceah Andrei D.
- Llanto, John Christian C.
- Mones, Rhynce Bryan F.
- Ogayon, Dave Laurence S.

**Adviser:** Jocelyn G. Landeral  
**Institution:** City College of Calamba  
**Date:** November 2025

---

## 📝 License

MIT License - Educational Project

---

## 🆘 Support

For issues or questions, contact:
- Email: dsogayon@ccc.edu.ph
- GitHub: (Your Repository URL)

---

**Built with ❤️ using Clean Architecture & ISO/IEC 25010 Standards**