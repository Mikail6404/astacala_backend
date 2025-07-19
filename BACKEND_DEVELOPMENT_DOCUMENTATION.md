# 🏗️ Astacala Rescue Backend - Complete Development Documentation

**Project:** Astacala Rescue Mobile Backend API  
**Framework:** Laravel 11  
**Database:** MySQL (via XAMPP)  
**Authentication:** Laravel Sanctum (JWT)  
**Development Date:** July 16, 2025  
**Status:** ✅ COMPLETE - Production Ready  

---

## 📋 **DEVELOPMENT OVERVIEW**

### **🎯 Implementation Context**
This backend was developed to support the Astacala Rescue Mobile application - a disaster response management system for Indonesian volunteer organizations. The backend provides RESTful APIs for authentication, disaster reporting, user management, and real-time notifications.

### **📊 Development Status**
- ✅ **Laravel Project Setup:** Complete with proper configuration
- ✅ **Database Schema:** All migrations created and executed
- ✅ **API Controllers:** Full CRUD operations implemented  
- ✅ **Authentication System:** JWT-based auth with Laravel Sanctum
- ✅ **File Upload System:** Image handling for disaster reports
- ✅ **API Routes:** All endpoints configured and tested
- ✅ **Database Relationships:** Proper Eloquent relationships
- ✅ **Validation:** Comprehensive request validation
- ✅ **Error Handling:** Structured error responses

---

## 🏗️ **ARCHITECTURE OVERVIEW**

### **📁 Project Structure**
```
astacala-rescue-api/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── API/
│   │           ├── AuthController.php      # Authentication endpoints
│   │           ├── UserController.php      # User profile management
│   │           ├── DisasterReportController.php  # Report CRUD + file upload
│   │           └── NotificationController.php    # Notification system
│   └── Models/
│       ├── User.php                # User model with additional fields
│       ├── DisasterReport.php      # Disaster report with relationships
│       ├── ReportImage.php         # Image attachments
│       └── Notification.php        # Real-time notifications
├── database/
│   └── migrations/
│       ├── 2025_07_16_035151_create_users_table.php
│       ├── 2025_07_16_035204_create_disaster_reports_table.php
│       ├── 2025_07_16_041003_create_report_images_table.php
│       ├── 2025_07_16_041041_create_notifications_table.php
│       └── 2025_07_16_043537_add_additional_fields_to_users_table.php
├── routes/
│   └── api.php                     # All API endpoint definitions
├── config/
│   ├── database.php               # MySQL configuration
│   └── sanctum.php                # JWT authentication config
└── .env                           # Environment configuration
```

---

## 🗄️ **DATABASE SCHEMA**

### **📊 Database Configuration**
- **Database:** `astacala_rescue`
- **Connection:** MySQL via XAMPP
- **Timezone:** Asia/Jakarta
- **Charset:** utf8mb4_unicode_ci

### **📋 Tables & Relationships**

#### **1. Users Table**
```sql
CREATE TABLE `users` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL UNIQUE,
    `email_verified_at` timestamp NULL DEFAULT NULL,
    `password` varchar(255) NOT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `avatar` varchar(255) DEFAULT NULL,
    `role` enum('VOLUNTEER','COORDINATOR','ADMIN') NOT NULL DEFAULT 'VOLUNTEER',
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `location` json DEFAULT NULL,
    `bio` text DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `users_email_index` (`email`)
);
```

#### **2. Disaster Reports Table**
```sql
CREATE TABLE `disaster_reports` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `title` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `type` enum('EARTHQUAKE','FLOOD','FIRE','LANDSLIDE','TSUNAMI','VOLCANO','OTHER') NOT NULL,
    `severity` enum('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL,
    `status` enum('PENDING','VERIFIED','IN_PROGRESS','RESOLVED') NOT NULL DEFAULT 'PENDING',
    `location` json NOT NULL,
    `coordinates` point DEFAULT NULL,
    `metadata` json DEFAULT NULL,
    `verified_by` bigint(20) unsigned DEFAULT NULL,
    `verified_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `disaster_reports_user_id_foreign` (`user_id`),
    KEY `disaster_reports_verified_by_foreign` (`verified_by`),
    KEY `disaster_reports_type_index` (`type`),
    KEY `disaster_reports_severity_index` (`severity`),
    KEY `disaster_reports_status_index` (`status`),
    CONSTRAINT `disaster_reports_user_id_foreign` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `disaster_reports_verified_by_foreign` 
        FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
);
```

#### **3. Report Images Table**
```sql
CREATE TABLE `report_images` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `disaster_report_id` bigint(20) unsigned NOT NULL,
    `image_path` varchar(255) NOT NULL,
    `caption` varchar(500) DEFAULT NULL,
    `is_primary` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `report_images_disaster_report_id_foreign` (`disaster_report_id`),
    CONSTRAINT `report_images_disaster_report_id_foreign` 
        FOREIGN KEY (`disaster_report_id`) REFERENCES `disaster_reports` (`id`) ON DELETE CASCADE
);
```

#### **4. Notifications Table**
```sql
CREATE TABLE `notifications` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `title` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `type` enum('REPORT','ALERT','SYSTEM','UPDATE') NOT NULL,
    `data` json DEFAULT NULL,
    `read_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `notifications_user_id_foreign` (`user_id`),
    KEY `notifications_type_index` (`type`),
    CONSTRAINT `notifications_user_id_foreign` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

---

## 🔌 **API ENDPOINTS**

### **🔐 Authentication Endpoints**

#### **POST** `/api/register`
**Purpose:** User registration  
**Request:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "+628123456789",
    "role": "VOLUNTEER"
}
```
**Response (201):**
```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+628123456789",
            "role": "VOLUNTEER"
        },
        "token": "jwt_token_here"
    }
}
```

#### **POST** `/api/login`
**Purpose:** User authentication  
**Request:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```
**Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "VOLUNTEER"
        },
        "token": "jwt_token_here"
    }
}
```

#### **POST** `/api/logout`
**Purpose:** User logout  
**Headers:** `Authorization: Bearer {token}`  
**Response (200):**
```json
{
    "success": true,
    "message": "Logout successful"
}
```

### **👤 User Management Endpoints**

#### **GET** `/api/profile`
**Purpose:** Get user profile  
**Headers:** `Authorization: Bearer {token}`  
**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+628123456789",
        "avatar": "avatars/user_1.jpg",
        "role": "VOLUNTEER",
        "bio": "Volunteer rescue worker",
        "location": {
            "latitude": -6.2088,
            "longitude": 106.8456,
            "address": "Jakarta, Indonesia"
        }
    }
}
```

#### **PUT** `/api/profile`
**Purpose:** Update user profile  
**Headers:** `Authorization: Bearer {token}`  
**Request:**
```json
{
    "name": "John Doe Updated",
    "phone": "+628123456790",
    "bio": "Experienced rescue volunteer",
    "location": {
        "latitude": -6.2088,
        "longitude": 106.8456,
        "address": "Jakarta, Indonesia"
    }
}
```

#### **POST** `/api/profile/avatar`
**Purpose:** Upload user avatar  
**Headers:** `Authorization: Bearer {token}`  
**Content-Type:** `multipart/form-data`  
**Request:**
```
avatar: [image file]
```

### **📊 Disaster Report Endpoints**

#### **GET** `/api/reports`
**Purpose:** Get all disaster reports with filtering  
**Headers:** `Authorization: Bearer {token}`  
**Query Parameters:**
- `type`: Filter by disaster type
- `severity`: Filter by severity level
- `status`: Filter by report status
- `user_id`: Filter by reporter

**Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "title": "Earthquake in Jakarta",
            "description": "Strong earthquake felt in central Jakarta",
            "type": "EARTHQUAKE",
            "severity": "HIGH",
            "status": "VERIFIED",
            "location": {
                "latitude": -6.2088,
                "longitude": 106.8456,
                "address": "Jakarta, Indonesia"
            },
            "metadata": {
                "magnitude": "6.2",
                "depth": "10km"
            },
            "user": {
                "id": 1,
                "name": "John Doe"
            },
            "images": [
                {
                    "id": 1,
                    "image_path": "reports/1/image_1.jpg",
                    "caption": "Damage to building",
                    "is_primary": true
                }
            ],
            "created_at": "2025-07-16T10:30:00Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "total_pages": 5,
        "total_records": 47
    }
}
```

#### **POST** `/api/reports`
**Purpose:** Create new disaster report  
**Headers:** `Authorization: Bearer {token}`  
**Content-Type:** `multipart/form-data`  
**Request:**
```
title: "Earthquake in Jakarta"
description: "Strong earthquake felt in central Jakarta"
type: "EARTHQUAKE"
severity: "HIGH"
location[latitude]: -6.2088
location[longitude]: 106.8456
location[address]: "Jakarta, Indonesia"
metadata[magnitude]: "6.2"
metadata[depth]: "10km"
images[]: [image file 1]
images[]: [image file 2]
image_captions[]: "Building damage"
image_captions[]: "Road cracks"
```

#### **GET** `/api/reports/{id}`
**Purpose:** Get specific disaster report  
**Headers:** `Authorization: Bearer {token}`

#### **PUT** `/api/reports/{id}`
**Purpose:** Update disaster report  
**Headers:** `Authorization: Bearer {token}`

#### **GET** `/api/reports/statistics`
**Purpose:** Get dashboard statistics  
**Headers:** `Authorization: Bearer {token}`  
**Response (200):**
```json
{
    "success": true,
    "data": {
        "total_reports": 47,
        "reports_by_type": {
            "EARTHQUAKE": 12,
            "FLOOD": 18,
            "FIRE": 8,
            "LANDSLIDE": 5,
            "OTHER": 4
        },
        "reports_by_severity": {
            "LOW": 15,
            "MEDIUM": 20,
            "HIGH": 8,
            "CRITICAL": 4
        },
        "reports_by_status": {
            "PENDING": 12,
            "VERIFIED": 25,
            "IN_PROGRESS": 8,
            "RESOLVED": 2
        },
        "recent_reports": 7
    }
}
```

### **🔔 Notification Endpoints**

#### **GET** `/api/notifications`
**Purpose:** Get user notifications  
**Headers:** `Authorization: Bearer {token}`

#### **POST** `/api/notifications/{id}/read`
**Purpose:** Mark notification as read  
**Headers:** `Authorization: Bearer {token}`

---

## 🛠️ **IMPLEMENTATION DETAILS**

### **🔐 Authentication System**
- **Framework:** Laravel Sanctum for API token authentication
- **Token Type:** Personal Access Tokens (similar to JWT)
- **Security:** Password hashing with bcrypt
- **Middleware:** `auth:sanctum` for protected routes

### **📁 File Upload System**
- **Storage:** Local filesystem (`storage/app/public/`)
- **Supported Formats:** jpg, jpeg, png, gif, webp
- **Size Limit:** 10MB per image
- **Path Structure:**
  - Avatars: `avatars/user_{id}.{ext}`
  - Report Images: `reports/{report_id}/image_{timestamp}.{ext}`

### **🔄 Request Validation**
All endpoints implement comprehensive validation:
- **Required Fields:** Proper validation rules
- **Data Types:** Email, phone, coordinates validation
- **File Validation:** Image format and size checking
- **Enum Validation:** Disaster types, severity levels, user roles

### **📊 Database Relationships**
- **User → DisasterReports:** One-to-Many
- **DisasterReport → ReportImages:** One-to-Many  
- **User → Notifications:** One-to-Many
- **User → VerifiedReports:** One-to-Many (as verifier)

---

## ⚙️ **CONFIGURATION FILES**

### **📄 .env Configuration**
```env
APP_NAME="Astacala Rescue API"
APP_ENV=local
APP_KEY=base64:generated_key
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=astacala_rescue
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1

FILESYSTEM_DISK=local
```

### **🗄️ Database Configuration (config/database.php)**
```php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'astacala_rescue'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```

### **🔐 Sanctum Configuration (config/sanctum.php)**
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1')),
'guard' => ['web'],
'expiration' => null,
'middleware' => [
    'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
],
```

---

## 🚀 **DEPLOYMENT & TESTING**

### **📋 Development Setup**
1. **Requirements:**
   - PHP 8.2+
   - Composer
   - MySQL (XAMPP)
   - Laravel 11

2. **Installation Commands:**
   ```bash
   composer install
   php artisan key:generate
   php artisan migrate
   php artisan storage:link
   php artisan serve
   ```

### **🧪 API Testing**
- **Test File:** `test_api.php` (included in root)
- **Testing Tools:** Postman collection recommended
- **Endpoint Base:** `http://127.0.0.1:8000/api`

### **✅ Validation Checklist**
- ✅ All migrations executed successfully
- ✅ Authentication endpoints working
- ✅ CRUD operations functional
- ✅ File upload system operational
- ✅ Database relationships properly established
- ✅ Error handling implemented
- ✅ API responses consistent

---

## 🔮 **NEXT STEPS & INTEGRATION**

### **📱 Mobile App Integration**
1. **Replace Mock Data:** Update Flutter app to use real API endpoints
2. **HTTP Client:** Implement proper API client with error handling
3. **Authentication:** Integrate JWT token management
4. **File Upload:** Connect image upload functionality
5. **Testing:** Validate all 83+ tests still pass with real backend

### **🌐 Future Web Integration**
- Coordinate with Gibran's Laravel web platform
- Plan shared database schema
- Implement consistent API contracts
- Design unified deployment strategy

### **📊 Production Considerations**
- Database optimization and indexing
- Image storage migration to cloud (AWS S3, etc.)
- API rate limiting implementation
- Comprehensive logging system
- Security audit and penetration testing

---

## 👥 **DEVELOPMENT TEAM**

**Backend Developer:** Muhammad Mikail Gabril  
**Institution:** Universitas Telkom - D3 Sistem Informasi  
**Repository:** https://github.com/Mikail6404/astacala_rescue_mobile/tree/nitro-AN-515-57  
**Development Date:** July 16, 2025  

**Industry Partner:** Yayasan Astacala  
**Project Type:** Final Year Project (Tugas Akhir)  
**Status:** Production Ready for Mobile Integration  

---

*This documentation serves as a complete reference for the Astacala Rescue Mobile backend system. All components are production-ready and tested for integration with the mobile application.*
