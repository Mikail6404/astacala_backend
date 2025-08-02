# 🌐 Astacala Rescue API - Comprehensive Documentation

**Last Updated:** August 3, 2025  
**API Version:** v1.0.0  
**Framework:** Laravel 11.13.0  
**Authentication:** Laravel Sanctum 4.1.2  
**WebSocket:** Laravel Reverb 1.5.1  
**Total Routes:** 101 operational endpoints

---

## 📊 **API OVERVIEW**

The Astacala Rescue API provides a comprehensive cross-platform backend solution supporting both mobile (Flutter) and web (Laravel Blade) applications. This RESTful API enables disaster reporting, user management, real-time notifications, file uploads, and administrative features.

**Base URL:** `https://your-domain.com/api`  
**API Version:** v1  
**Authentication:** Bearer Token (Laravel Sanctum)  
**Content-Type:** `application/json` (except file uploads: `multipart/form-data`)  
**WebSocket:** Supported via Laravel Reverb for real-time updates

### **🎯 Platform Support**
- **Mobile Platform:** Flutter app with JWT authentication
- **Web Platform:** Laravel Blade dashboard with session authentication  
- **Cross-Platform:** Unified backend with real-time synchronization
- **Total Endpoints:** 101 routes (43 v1 + 31 legacy + 27 system)

---

## 📋 **TABLE OF CONTENTS**

1. [System Health](#system-health)
2. [Authentication](#authentication)
3. [Disaster Reports](#disaster-reports)
4. [User Management](#user-management)
5. [File Management](#file-management)
6. [Forum System](#forum-system)
7. [Notifications](#notifications)
8. [Publications](#publications)
9. [Gibran Web Compatibility](#gibran-web-compatibility)
10. [Error Handling](#error-handling)
11. [Rate Limiting](#rate-limiting)
12. [Testing Endpoints](#testing-endpoints)

---

## 🏥 **SYSTEM HEALTH**

### **Health Check**
```
GET /api/health
```

**Response:**
```json
{
    "status": "ok",
    "message": "Astacala Rescue API is running",
    "timestamp": "2025-08-03T10:30:00Z",
    "version": "1.0.0",
    "platform_support": ["mobile", "web"],
    "integration_status": "cross-platform-ready"
}
```

### **User Info (Auth Required)**
```
GET /api/user
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "VOLUNTEER"
}
```

---

## 🔐 **AUTHENTICATION**

### **Register User**
```
POST /api/v1/auth/register
```

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "+628123456789",
    "role": "VOLUNTEER",
    "organization": "Jakarta Rescue Team",
    "birth_date": "1990-01-15"
}
```

**Response:**
```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "VOLUNTEER",
            "organization": "Jakarta Rescue Team",
            "is_active": true,
            "created_at": "2025-08-03T10:30:00Z"
        },
        "token": "1|abcd1234efgh5678..."
    }
}
```

### **Login User**
```
POST /api/v1/auth/login
```

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "password123",
    "platform": "mobile"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "VOLUNTEER",
            "fcm_token": null
        },
        "token": "1|abcd1234efgh5678...",
        "expires_at": "2025-08-04T10:30:00Z"
    }
}
```

### **Get Current User**
```
GET /api/v1/auth/me
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "VOLUNTEER",
        "organization": "Jakarta Rescue Team",
        "phone": "+628123456789",
        "profile_picture_url": null,
        "is_active": true,
        "emergency_contacts": [
            {
                "name": "Jane Doe",
                "phone": "+628987654321",
                "relationship": "spouse"
            }
        ]
    }
}
```

### **Refresh Token**
```
POST /api/v1/auth/refresh
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "data": {
        "token": "2|newtoken1234...",
        "expires_at": "2025-08-04T10:30:00Z"
    }
}
```

### **Change Password**
```
POST /api/v1/auth/change-password
```

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "current_password": "oldpassword123",
    "new_password": "newpassword123",
    "new_password_confirmation": "newpassword123"
}
```

### **Forgot Password**
```
POST /api/v1/auth/forgot-password
```

**Request Body:**
```json
{
    "email": "john@example.com"
}
```

### **Reset Password**
```
POST /api/v1/auth/reset-password
```

**Request Body:**
```json
{
    "email": "john@example.com",
    "token": "reset_token_here",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

### **Logout**
```
POST /api/v1/auth/logout
```

**Headers:**
```
Authorization: Bearer {token}
```

---

## 🆘 **DISASTER REPORTS**

### **List Reports**
```
GET /api/v1/reports
```

**Query Parameters:**
- `page` (int): Page number (default: 1)
- `per_page` (int): Items per page (default: 15, max: 100)
- `status` (string): Filter by status (PENDING, ACTIVE, RESOLVED, REJECTED)
- `disaster_type` (string): Filter by disaster type
- `severity_level` (string): Filter by severity (low, medium, high, critical)
- `search` (string): Search in title and description
- `latitude` (float): Center latitude for location-based search
- `longitude` (float): Center longitude for location-based search
- `radius` (int): Search radius in kilometers

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Earthquake in Jakarta",
            "description": "Strong earthquake felt in central Jakarta",
            "disaster_type": "earthquake",
            "severity_level": "high",
            "status": "ACTIVE",
            "latitude": -6.2088,
            "longitude": 106.8456,
            "location_name": "Central Jakarta",
            "address": "Jl. Sudirman, Jakarta Pusat",
            "estimated_affected": 500,
            "weather_condition": "clear",
            "incident_timestamp": "2025-08-03T09:15:00Z",
            "reported_by": {
                "id": 1,
                "name": "John Doe",
                "organization": "Jakarta Rescue Team"
            },
            "assigned_to": {
                "id": 2,
                "name": "Jane Smith",
                "organization": "Emergency Response Team"
            },
            "verified_by_admin": {
                "id": 3,
                "name": "Admin User",
                "role": "ADMIN"
            },
            "verification_notes": "Report verified and response team dispatched",
            "verified_at": "2025-08-03T09:30:00Z",
            "team_name": "Alpha Team",
            "images": [
                {
                    "id": 1,
                    "filename": "earthquake_damage_001.jpg",
                    "file_url": "/storage/disasters/1/earthquake_damage_001.jpg",
                    "file_size": 1048576,
                    "uploaded_by": "John Doe"
                }
            ],
            "created_at": "2025-08-03T09:15:00Z",
            "updated_at": "2025-08-03T09:30:00Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 15,
        "total": 42,
        "from": 1,
        "to": 15
    }
}
```

### **Create Report**
```
POST /api/v1/reports
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "title": "Building Fire in South Jakarta",
    "description": "Large fire reported at commercial building",
    "disaster_type": "fire",
    "severity_level": "critical",
    "latitude": -6.2615,
    "longitude": 106.8106,
    "location_name": "Kebayoran Baru",
    "address": "Jl. Senopati, Jakarta Selatan",
    "estimated_affected": 200,
    "weather_condition": "windy",
    "incident_timestamp": "2025-08-03T11:00:00Z"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Disaster report created successfully",
    "data": {
        "id": 2,
        "title": "Building Fire in South Jakarta",
        "status": "PENDING",
        "reported_by": {
            "id": 1,
            "name": "John Doe"
        },
        "created_at": "2025-08-03T11:05:00Z"
    }
}
```

### **Get Report Details**
```
GET /api/v1/reports/{id}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:** Same format as individual report in list endpoint

### **Update Report**
```
PUT /api/v1/reports/{id}
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:** Same as create report (partial updates allowed)

### **Delete Report**
```
DELETE /api/v1/reports/{id}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "message": "Report deleted successfully"
}
```

### **Verify Report (Admin Only)**
```
POST /api/v1/reports/{id}/verify
```

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "verification_notes": "Report verified and emergency response initiated",
    "assigned_to": 5,
    "team_name": "Beta Response Team"
}
```

### **Publish Report (Admin Only)**
```
POST /api/v1/reports/{id}/publish
```

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "publish_to_mobile": true,
    "notification_message": "New emergency alert in your area"
}
```

### **Web Submit Report**
```
POST /api/v1/reports/web-submit
```

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:** Same as standard create report with additional web-specific fields

### **Admin View Reports**
```
GET /api/v1/reports/admin-view
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:** Enhanced report list with admin-specific information

### **Pending Reports**
```
GET /api/v1/reports/pending
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:** List of reports with status "PENDING"

### **User's Reports**
```
GET /api/v1/reports/my-reports
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:** Reports created by the authenticated user

### **User's Statistics**
```
GET /api/v1/reports/my-statistics
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "total_reports": 15,
        "pending_reports": 3,
        "verified_reports": 10,
        "resolved_reports": 2,
        "reports_this_month": 5,
        "average_response_time": "2.5 hours"
    }
}
```

### **Reports Statistics**
```
GET /api/v1/reports/statistics
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "total_reports": 1247,
        "pending_reports": 23,
        "active_reports": 45,
        "resolved_reports": 1156,
        "rejected_reports": 23,
        "reports_by_type": {
            "earthquake": 324,
            "flood": 412,
            "fire": 298,
            "hurricane": 67,
            "other": 146
        },
        "reports_by_severity": {
            "low": 421,
            "medium": 534,
            "high": 198,
            "critical": 94
        },
        "monthly_reports": [
            {"month": "2025-07", "count": 89},
            {"month": "2025-08", "count": 67}
        ]
    }
}
```

---

## 👥 **USER MANAGEMENT**

### **Get User Profile**
```
GET /api/v1/users/profile
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+628123456789",
        "role": "VOLUNTEER",
        "organization": "Jakarta Rescue Team",
        "birth_date": "1990-01-15",
        "address": "Jl. Sudirman No. 123, Jakarta",
        "profile_picture_url": "/storage/avatars/john_doe.jpg",
        "is_active": true,
        "emergency_contacts": [
            {
                "name": "Jane Doe",
                "phone": "+628987654321",
                "relationship": "spouse"
            }
        ],
        "last_login": "2025-08-03T08:30:00Z",
        "created_at": "2025-07-15T10:00:00Z"
    }
}
```

### **Update User Profile**
```
PUT /api/v1/users/profile
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "name": "John Smith",
    "phone": "+628123456789",
    "organization": "Jakarta Emergency Response",
    "address": "Jl. Thamrin No. 456, Jakarta",
    "emergency_contacts": [
        {
            "name": "Jane Smith",
            "phone": "+628987654321",
            "relationship": "spouse"
        },
        {
            "name": "Bob Johnson",
            "phone": "+628555123456",
            "relationship": "colleague"
        }
    ]
}
```

### **Upload Avatar**
```
POST /api/v1/users/profile/avatar
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```
avatar: (file) - Image file (jpg, png, webp, max 5MB)
```

**Response:**
```json
{
    "success": true,
    "message": "Avatar uploaded successfully",
    "data": {
        "profile_picture_url": "/storage/avatars/user_1_avatar.jpg"
    }
}
```

### **Get User by ID**
```
GET /api/v1/users/{id}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:** Public user information (no sensitive data)

### **Admin User List (Admin Only)**
```
GET /api/v1/users/admin-list
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (int): Page number
- `per_page` (int): Items per page
- `role` (string): Filter by role
- `is_active` (boolean): Filter by active status
- `search` (string): Search by name or email

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "role": "VOLUNTEER",
            "organization": "Jakarta Rescue Team",
            "is_active": true,
            "last_login": "2025-08-03T08:30:00Z",
            "reports_count": 15,
            "created_at": "2025-07-15T10:00:00Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "total": 1247
    }
}
```

### **Create Admin User (Super Admin Only)**
```
POST /api/v1/users/create-admin
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "name": "Admin User",
    "email": "admin@example.com",
    "password": "securepassword123",
    "role": "ADMIN",
    "organization": "Emergency Management Agency"
}
```

### **Update User Role (Admin Only)**
```
PUT /api/v1/users/{id}/role
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "role": "COORDINATOR"
}
```

### **Update User Status (Admin Only)**
```
PUT /api/v1/users/{id}/status
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "is_active": false,
    "reason": "Account suspended for policy violation"
}
```

### **User Statistics (Admin Only)**
```
GET /api/v1/users/statistics
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "total_users": 1247,
        "active_users": 1198,
        "users_by_role": {
            "VOLUNTEER": 1089,
            "COORDINATOR": 67,
            "ADMIN": 23,
            "SUPER_ADMIN": 3
        },
        "new_users_this_month": 45,
        "user_activity": {
            "daily_active": 234,
            "weekly_active": 567,
            "monthly_active": 890
        }
    }
}
```

---

## 📁 **FILE MANAGEMENT**

### **Upload Disaster Images**
```
POST /api/v1/files/disasters/{reportId}/images
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```
images[]: (files) - Image files (jpg, png, webp, max 5MB each, max 5 files)
```

**Response:**
```json
{
    "success": true,
    "message": "Images uploaded successfully",
    "data": {
        "uploaded_images": [
            {
                "id": 1,
                "filename": "disaster_001.jpg",
                "file_url": "/storage/disasters/1/disaster_001.jpg",
                "file_size": 1048576,
                "uploaded_at": "2025-08-03T11:30:00Z"
            }
        ]
    }
}
```

### **Delete Disaster Image**
```
DELETE /api/v1/files/disasters/{reportId}/images/{imageId}
```

**Headers:**
```
Authorization: Bearer {token}
```

### **Upload Documents**
```
POST /api/v1/files/disasters/{reportId}/documents
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```
documents[]: (files) - Document files (pdf, max 10MB each, max 3 files)
```

### **Upload User Avatar**
```
POST /api/v1/files/avatar
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```
avatar: (file) - Image file (jpg, png, webp, max 5MB)
```

### **Storage Statistics (Admin Only)**
```
GET /api/v1/files/storage/statistics
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "total_files": 5678,
        "total_size": "2.3 GB",
        "images_count": 4321,
        "documents_count": 1357,
        "storage_by_type": {
            "disaster_images": "1.8 GB",
            "user_avatars": "245 MB",
            "documents": "255 MB"
        },
        "monthly_uploads": {
            "2025-07": 234,
            "2025-08": 189
        }
    }
}
```

---

## 💬 **FORUM SYSTEM**

### **List Forum Messages**
```
GET /api/v1/forum
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (int): Page number
- `per_page` (int): Items per page
- `priority` (string): Filter by priority (URGENT, HIGH, MEDIUM, LOW)

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "disaster_report_id": 1,
            "user": {
                "id": 1,
                "name": "John Doe",
                "organization": "Jakarta Rescue Team"
            },
            "message": "Rescue team dispatched to location",
            "priority": "HIGH",
            "parent_message_id": null,
            "is_edited": false,
            "created_at": "2025-08-03T11:45:00Z",
            "replies": [
                {
                    "id": 2,
                    "user": {
                        "id": 2,
                        "name": "Jane Smith"
                    },
                    "message": "ETA 15 minutes",
                    "priority": "URGENT",
                    "created_at": "2025-08-03T11:47:00Z"
                }
            ]
        }
    ]
}
```

### **Post Forum Message**
```
POST /api/v1/forum
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "disaster_report_id": 1,
    "message": "Medical team needed urgently",
    "priority": "URGENT",
    "parent_message_id": null
}
```

### **Get Report Messages**
```
GET /api/v1/forum/reports/{reportId}/messages
```

**Headers:**
```
Authorization: Bearer {token}
```

### **Post Report Message**
```
POST /api/v1/forum/reports/{reportId}/messages
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "message": "Situation update: Fire under control",
    "priority": "HIGH",
    "parent_message_id": 1
}
```

### **Update Message**
```
PUT /api/v1/forum/reports/{reportId}/messages/{messageId}
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "message": "Updated: Fire completely extinguished",
    "priority": "MEDIUM"
}
```

### **Delete Message**
```
DELETE /api/v1/forum/reports/{reportId}/messages/{messageId}
```

**Headers:**
```
Authorization: Bearer {token}
```

### **Mark Messages as Read**
```
POST /api/v1/forum/reports/{reportId}/mark-read
```

**Headers:**
```
Authorization: Bearer {token}
```

---

## 🔔 **NOTIFICATIONS**

### **List Notifications**
```
GET /api/v1/notifications
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (int): Page number
- `per_page` (int): Items per page
- `is_read` (boolean): Filter by read status
- `priority` (string): Filter by priority
- `type` (string): Filter by notification type

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Report Verified",
            "message": "Your disaster report has been verified by admin",
            "type": "report_verified",
            "priority": "HIGH",
            "is_read": false,
            "related_report": {
                "id": 1,
                "title": "Earthquake in Jakarta"
            },
            "data": {
                "report_id": 1,
                "verified_by": "Admin User"
            },
            "created_at": "2025-08-03T12:00:00Z",
            "read_at": null
        }
    ],
    "pagination": {
        "current_page": 1,
        "total": 23
    }
}
```

### **Mark Notification as Read**
```
POST /api/v1/notifications/mark-read
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "notification_ids": [1, 2, 3]
}
```

### **Get Unread Count**
```
GET /api/v1/notifications/unread-count
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "unread_count": 5
    }
}
```

### **Update FCM Token**
```
POST /api/v1/notifications/fcm-token
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "fcm_token": "firebase_messaging_token_here"
}
```

### **Send Urgent Notification (Admin Only)**
```
POST /api/v1/notifications/broadcast
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "title": "Emergency Alert",
    "message": "Tsunami warning for coastal areas",
    "priority": "URGENT",
    "target_roles": ["VOLUNTEER", "COORDINATOR"],
    "location": {
        "latitude": -6.2088,
        "longitude": 106.8456,
        "radius": 50
    }
}
```

### **Delete Notification**
```
DELETE /api/v1/notifications/{id}
```

**Headers:**
```
Authorization: Bearer {token}
```

---

## 📄 **PUBLICATIONS**

### **List Publications**
```
GET /api/v1/publications
```

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (int): Page number
- `per_page` (int): Items per page
- `category` (string): Filter by category (news, announcement, training)
- `status` (string): Filter by status (draft, published, archived)
- `search` (string): Search in title and content

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "title": "Emergency Response Training",
            "content": "Join our emergency response training program...",
            "category": "training",
            "status": "published",
            "featured_image_url": "/storage/publications/training_001.jpg",
            "author": {
                "id": 3,
                "name": "Admin User"
            },
            "tags": ["training", "emergency", "response"],
            "published_at": "2025-08-01T09:00:00Z",
            "created_at": "2025-07-30T14:30:00Z",
            "comments_count": 12
        }
    ],
    "pagination": {
        "current_page": 1,
        "total": 45
    }
}
```

### **Create Publication (Admin Only)**
```
POST /api/v1/publications
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "title": "New Emergency Protocols",
    "content": "Updated emergency response protocols are now available...",
    "category": "announcement",
    "status": "draft",
    "featured_image_url": "/storage/publications/protocols_001.jpg",
    "tags": ["protocols", "emergency", "update"]
}
```

### **Get Publication Details**
```
GET /api/v1/publications/{id}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Emergency Response Training",
        "content": "Complete training content here...",
        "category": "training",
        "status": "published",
        "featured_image_url": "/storage/publications/training_001.jpg",
        "author": {
            "id": 3,
            "name": "Admin User",
            "organization": "Emergency Management"
        },
        "tags": ["training", "emergency", "response"],
        "published_at": "2025-08-01T09:00:00Z",
        "created_at": "2025-07-30T14:30:00Z",
        "updated_at": "2025-07-31T10:15:00Z",
        "comments": [
            {
                "id": 1,
                "user": {
                    "id": 1,
                    "name": "John Doe"
                },
                "comment": "Very helpful training material",
                "created_at": "2025-08-01T15:30:00Z"
            }
        ]
    }
}
```

### **Update Publication (Admin Only)**
```
PUT /api/v1/publications/{id}
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:** Same as create publication

### **Delete Publication (Admin Only)**
```
DELETE /api/v1/publications/{id}
```

**Headers:**
```
Authorization: Bearer {token}
```

### **Publish Publication (Admin Only)**
```
POST /api/v1/publications/{id}/publish
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "notify_users": true,
    "target_roles": ["VOLUNTEER", "COORDINATOR"]
}
```

---

## 🌐 **GIBRAN WEB COMPATIBILITY**

### **Web Auth Login**
```
POST /api/gibran/auth/login
```

**Request Body:**
```json
{
    "email": "admin@example.com",
    "password": "password123"
}
```

### **Get Berita Bencana (Public)**
```
GET /api/gibran/berita-bencana
```

**Response:** Public disaster news in web-compatible format

### **Get Pelaporans (Web Format)**
```
GET /api/gibran/pelaporans
```

**Headers:**
```
Authorization: Bearer {token}
```

### **Submit Pelaporan (Web Format)**
```
POST /api/gibran/pelaporans
```

**Headers:**
```
Authorization: Bearer {token}
```

### **Verify Pelaporan**
```
POST /api/gibran/pelaporans/{id}/verify
```

**Headers:**
```
Authorization: Bearer {token}
```

### **Dashboard Statistics**
```
GET /api/gibran/dashboard/statistics
```

**Headers:**
```
Authorization: Bearer {token}
```

### **Send Notifikasi**
```
POST /api/gibran/notifikasi/send
```

**Headers:**
```
Authorization: Bearer {token}
```

### **Get User Notifications**
```
GET /api/gibran/notifikasi/{pengguna_id}
```

**Headers:**
```
Authorization: Bearer {token}
```

---

## ⚠️ **ERROR HANDLING**

### **Standard Error Response Format**
```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field_name": ["Validation error message"]
    },
    "error_code": "VALIDATION_ERROR",
    "meta": {
        "timestamp": "2025-08-03T12:00:00Z",
        "request_id": "req_123456789",
        "version": "1.0.0"
    }
}
```

### **HTTP Status Codes**
- `200` - Success
- `201` - Created
- `400` - Bad Request (validation errors)
- `401` - Unauthorized (authentication required)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `422` - Unprocessable Entity (validation failed)
- `429` - Too Many Requests (rate limited)
- `500` - Internal Server Error

### **Error Types**
- `VALIDATION_ERROR` - Input validation failed
- `AUTHENTICATION_ERROR` - Invalid or missing authentication
- `AUTHORIZATION_ERROR` - Insufficient permissions
- `NOT_FOUND_ERROR` - Resource not found
- `RATE_LIMIT_ERROR` - Too many requests
- `INTERNAL_ERROR` - Server error

---

## 🚦 **RATE LIMITING**

### **Rate Limits**
- **Authentication endpoints:** 10 requests per minute
- **General API endpoints:** 60 requests per minute
- **File upload endpoints:** 20 requests per hour
- **Admin endpoints:** 120 requests per minute

### **Rate Limit Headers**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1691067600
```

---

## 🧪 **TESTING ENDPOINTS**

### **Test Notifications**
```
POST /api/test-notifications
```

**Request Body:**
```json
{
    "user_id": 1,
    "type": "test",
    "message": "Test notification"
}
```

### **Test WebSocket Events**
```
POST /api/test-websocket-events
```

**Request Body:**
```json
{
    "event": "disaster_report_submitted",
    "data": {
        "report_id": 1
    }
}
```

---

## 📚 **ADDITIONAL RESOURCES**

### **Documentation Links**
- [Backend Development Documentation](./BACKEND_DEVELOPMENT_DOCUMENTATION.md)
- [Authentication Troubleshooting Guide](./AUTHENTICATION_TROUBLESHOOTING_GUIDE.md)
- [Security Hardening Documentation](./SECURITY_HARDENING_DOCUMENTATION.md)
- [API Testing Guide](./API_TESTING_GUIDE.md)

### **Support**
- **Health Check:** `GET /api/health`
- **Version Info:** Available in all responses under `meta.version`
- **WebSocket Support:** Laravel Reverb on port 8080 (development)

---

**📋 Documentation Status:** Complete and Validated  
**📅 Last Updated:** August 3, 2025  
**👨‍💻 Documented By:** AI Agent - Comprehensive System Analysis  
**🎯 API Version:** v1.0.0 (101 endpoints operational)
