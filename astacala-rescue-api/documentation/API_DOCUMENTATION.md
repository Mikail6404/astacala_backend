# Astacala Rescue API Documentation

## Overview

The Astacala Rescue API provides a comprehensive cross-platform backend solution supporting both mobile (Flutter) and web (Gibran dashboard) applications. This RESTful API enables disaster reporting, user management, real-time notifications, file uploads, and administrative features.

**Base URL:** `https://your-domain.com/api`  
**API Version:** v1  
**Authentication:** Bearer Token (Laravel Sanctum)  
**Content-Type:** `application/json` (except file uploads: `multipart/form-data`)

## Table of Contents

1. [Authentication](#authentication)
2. [Health Check](#health-check)
3. [Disaster Reports](#disaster-reports)
4. [File Upload System](#file-upload-system)
5. [User Management](#user-management)
6. [Cross-Platform Notifications](#cross-platform-notifications)
7. [Publications](#publications)
8. [Forum Messages](#forum-messages)
9. [Gibran Web Compatibility](#gibran-web-compatibility)
10. [Error Handling](#error-handling)
11. [Rate Limiting](#rate-limiting)
12. [Testing Endpoints](#testing-endpoints)

---

## Authentication

### Register User
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
    "role": "VOLUNTEER"
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
            "is_active": true
        },
        "token": "1|abcd1234efgh5678..."
    }
}
```

### Login User
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
            "role": "VOLUNTEER"
        },
        "token": "1|abcd1234efgh5678...",
        "expires_at": "2025-08-29T10:30:00Z"
    }
}
```

### Get Current User
```
GET /api/v1/auth/me
Authorization: Bearer {token}
```

### Logout
```
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

### Change Password
```
POST /api/v1/auth/change-password
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

---

## Health Check

### API Health Status
```
GET /api/health
```

**Response:**
```json
{
    "status": "ok",
    "message": "Astacala Rescue API is running",
    "timestamp": "2025-07-30T10:30:00Z",
    "version": "1.0.0",
    "platform_support": ["mobile", "web"],
    "integration_status": "cross-platform-ready"
}
```

---

## Disaster Reports

### List All Reports
```
GET /api/v1/reports
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (int): Page number (default: 1)
- `per_page` (int): Items per page (default: 15, max: 100)
- `status` (string): Filter by status (PENDING, VERIFIED, PUBLISHED, REJECTED)
- `disaster_type` (string): Filter by type (FLOOD, EARTHQUAKE, FIRE, etc.)
- `severity_level` (string): Filter by severity (LOW, MEDIUM, HIGH, CRITICAL)
- `platform` (string): Filter by platform (mobile, web)

**Response:**
```json
{
    "success": true,
    "data": {
        "reports": [
            {
                "id": 1,
                "title": "Flood in Jakarta",
                "description": "Heavy flooding in central Jakarta",
                "disaster_type": "FLOOD",
                "severity_level": "HIGH",
                "status": "VERIFIED",
                "latitude": -6.2088,
                "longitude": 106.8456,
                "location_name": "Jakarta, Indonesia",
                "estimated_affected": 500,
                "incident_timestamp": "2025-07-30T08:00:00Z",
                "reported_by": {
                    "id": 1,
                    "name": "John Doe",
                    "email": "john@example.com"
                },
                "images": [
                    {
                        "id": 1,
                        "original_filename": "flood_image.jpg",
                        "url": "/storage/disasters/flood_image_optimized.jpg",
                        "thumbnail_url": "/storage/disasters/thumbnails/flood_image_thumb.jpg",
                        "is_primary": true
                    }
                ],
                "created_at": "2025-07-30T08:15:00Z",
                "updated_at": "2025-07-30T09:00:00Z"
            }
        ],
        "meta": {
            "current_page": 1,
            "total_pages": 5,
            "total_count": 67,
            "per_page": 15
        }
    }
}
```

### Create New Report
```
POST /api/v1/reports
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "title": "Forest Fire in Sumatra",
    "description": "Large forest fire spreading rapidly",
    "disaster_type": "FIRE",
    "severity_level": "CRITICAL",
    "latitude": -0.7893,
    "longitude": 113.9213,
    "location_name": "Sumatra, Indonesia",
    "estimated_affected": 1000,
    "incident_timestamp": "2025-07-30T10:00:00Z",
    "platform": "mobile"
}
```

### Get Report by ID
```
GET /api/v1/reports/{id}
Authorization: Bearer {token}
```

### Update Report
```
PUT /api/v1/reports/{id}
Authorization: Bearer {token}
```

### Delete Report
```
DELETE /api/v1/reports/{id}
Authorization: Bearer {token}
```

### Get Report Statistics
```
GET /api/v1/reports/statistics
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "total_reports": 150,
        "by_status": {
            "PENDING": 25,
            "VERIFIED": 80,
            "PUBLISHED": 40,
            "REJECTED": 5
        },
        "by_disaster_type": {
            "FLOOD": 60,
            "EARTHQUAKE": 30,
            "FIRE": 25,
            "LANDSLIDE": 20,
            "TSUNAMI": 15
        },
        "by_severity": {
            "LOW": 40,
            "MEDIUM": 60,
            "HIGH": 35,
            "CRITICAL": 15
        },
        "by_platform": {
            "mobile": 120,
            "web": 30
        },
        "recent_activity": {
            "last_24h": 12,
            "last_7d": 45,
            "last_30d": 98
        }
    }
}
```

### Admin: Verify Report
```
POST /api/v1/reports/{id}/verify
Authorization: Bearer {token}
Middleware: role:admin,super_admin
```

**Request Body:**
```json
{
    "verification_notes": "Report verified by field team",
    "admin_comments": "Location confirmed, severity assessed"
}
```

### Admin: Publish Report
```
POST /api/v1/reports/{id}/publish
Authorization: Bearer {token}
Middleware: role:admin,super_admin
```

---

## File Upload System

### Upload Disaster Report Images
```
POST /api/v1/files/disasters/{reportId}/images
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Data:**
- `images[]` (file): Multiple image files (max 10 files)
- `is_primary` (boolean): Mark first image as primary (optional)
- `platform` (string): Platform identifier (mobile/web)

**File Constraints:**
- Max file size: 10MB per image
- Supported formats: JPEG, PNG, WebP
- Auto-optimization: Resized to max 1920x1080
- Thumbnail generation: 300x200px

**Response:**
```json
{
    "success": true,
    "message": "Images uploaded successfully",
    "data": {
        "uploaded_images": [
            {
                "id": 15,
                "original_filename": "disaster_scene.jpg",
                "url": "/storage/disasters/2025/07/disaster_scene_optimized.jpg",
                "thumbnail_url": "/storage/disasters/2025/07/thumbnails/disaster_scene_thumb.jpg",
                "file_size": 2048576,
                "mime_type": "image/jpeg",
                "is_primary": true,
                "metadata": {
                    "width": 1920,
                    "height": 1080,
                    "gps_coordinates": {
                        "latitude": -6.2088,
                        "longitude": 106.8456
                    }
                },
                "uploaded_at": "2025-07-30T10:30:00Z"
            }
        ],
        "statistics": {
            "total_uploaded": 1,
            "total_size": "2.0 MB",
            "processing_time": "1.2s"
        }
    }
}
```

### Upload User Avatar
```
POST /api/v1/files/avatar
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Data:**
- `avatar` (file): Single image file
- `platform` (string): Platform identifier

**Response:**
```json
{
    "success": true,
    "message": "Avatar uploaded successfully",
    "data": {
        "avatar": {
            "url": "/storage/avatars/user_1_avatar.jpg",
            "thumbnail_url": "/storage/avatars/thumbnails/user_1_avatar_thumb.jpg",
            "file_size": 512000,
            "uploaded_at": "2025-07-30T10:30:00Z"
        }
    }
}
```

### Upload Documents
```
POST /api/v1/files/disasters/{reportId}/documents
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Data:**
- `documents[]` (file): Multiple document files
- `document_type` (string): Type of document (OFFICIAL_REPORT, WITNESS_STATEMENT, etc.)

**Supported Formats:** PDF, DOC, DOCX, TXT  
**Max File Size:** 20MB per document

### Delete Image
```
DELETE /api/v1/files/disasters/{reportId}/images/{imageId}
Authorization: Bearer {token}
```

### Get Storage Statistics (Admin Only)
```
GET /api/v1/files/storage/statistics
Authorization: Bearer {token}
Middleware: role:admin,super_admin
```

**Response:**
```json
{
    "success": true,
    "data": {
        "total_storage_used": "15.7 GB",
        "by_type": {
            "disaster_images": "12.3 GB",
            "user_avatars": "1.2 GB",
            "documents": "2.2 GB"
        },
        "by_platform": {
            "mobile": "10.5 GB",
            "web": "5.2 GB"
        },
        "file_counts": {
            "total_files": 3247,
            "disaster_images": 2891,
            "user_avatars": 245,
            "documents": 111
        },
        "monthly_upload_trends": [
            {"month": "2025-07", "size": "2.3 GB", "count": 456},
            {"month": "2025-06", "size": "1.8 GB", "count": 332}
        ]
    }
}
```

---

## User Management

### Get User Profile
```
GET /api/v1/users/profile
Authorization: Bearer {token}
```

### Update User Profile
```
PUT /api/v1/users/profile
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "name": "John Doe Updated",
    "phone": "+628123456789",
    "address": "Jakarta, Indonesia",
    "emergency_contact": "+628987654321",
    "platform_preferences": {
        "mobile": {
            "push_notifications": true,
            "emergency_alerts": true
        },
        "web": {
            "email_notifications": true,
            "desktop_notifications": false
        }
    }
}
```

### Get User Reports
```
GET /api/v1/users/reports
Authorization: Bearer {token}
```

### Admin: List All Users
```
GET /api/v1/users/admin-list
Authorization: Bearer {token}
Middleware: role:admin,super_admin
```

### Admin: Update User Role
```
PUT /api/v1/users/{id}/role
Authorization: Bearer {token}
Middleware: role:admin,super_admin
```

**Request Body:**
```json
{
    "role": "ADMIN",
    "reason": "Promoted to administrator"
}
```

### Admin: Update User Status
```
PUT /api/v1/users/{id}/status
Authorization: Bearer {token}
Middleware: role:admin,super_admin
```

**Request Body:**
```json
{
    "is_active": false,
    "reason": "Account suspended for policy violation"
}
```

---

## Cross-Platform Notifications

### Get User Notifications
```
GET /api/v1/notifications
Authorization: Bearer {token}
```

**Query Parameters:**
- `platform` (string): Filter by platform (mobile, web)
- `type` (string): Filter by type (DISASTER_ALERT, REPORT_UPDATE, SYSTEM_MESSAGE)
- `read` (boolean): Filter by read status
- `page` (int): Page number
- `per_page` (int): Items per page

**Response:**
```json
{
    "success": true,
    "data": {
        "notifications": [
            {
                "id": 1,
                "type": "DISASTER_ALERT",
                "title": "New High-Severity Report",
                "message": "A critical flood report has been submitted in your area",
                "data": {
                    "report_id": 15,
                    "disaster_type": "FLOOD",
                    "severity_level": "CRITICAL",
                    "location": "Jakarta, Indonesia"
                },
                "platform": "mobile",
                "is_read": false,
                "created_at": "2025-07-30T10:15:00Z",
                "read_at": null
            }
        ],
        "meta": {
            "total_count": 25,
            "unread_count": 8,
            "current_page": 1,
            "total_pages": 3
        }
    }
}
```

### Get Unread Count
```
GET /api/v1/notifications/unread-count
Authorization: Bearer {token}
```

**Query Parameters:**
- `platform` (string): Platform filter (mobile, web)

**Response:**
```json
{
    "success": true,
    "data": {
        "unread_count": 8,
        "platform": "mobile",
        "by_type": {
            "DISASTER_ALERT": 3,
            "REPORT_UPDATE": 4,
            "SYSTEM_MESSAGE": 1
        }
    }
}
```

### Mark Notifications as Read
```
POST /api/v1/notifications/mark-read
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "notification_ids": [1, 2, 3],
    "platform": "mobile"
}
```

### Register FCM Token
```
POST /api/v1/notifications/fcm-token
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "fcm_token": "dGhpcyBpcyBhIGZha2UgdG9rZW4...",
    "platform": "mobile",
    "device_info": {
        "device_type": "android",
        "app_version": "1.0.0",
        "os_version": "Android 13"
    }
}
```

### Admin: Send Urgent Broadcast
```
POST /api/v1/notifications/broadcast
Authorization: Bearer {token}
Middleware: role:admin,super_admin
```

**Request Body:**
```json
{
    "title": "Emergency Alert",
    "message": "Tsunami warning issued for coastal areas",
    "type": "EMERGENCY_ALERT",
    "target_platforms": ["mobile", "web"],
    "target_roles": ["VOLUNTEER", "ADMIN"],
    "data": {
        "priority": "HIGH",
        "action_required": true,
        "expiry_time": "2025-07-30T18:00:00Z"
    }
}
```

---

## Publications

### List Publications
```
GET /api/v1/publications
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` (string): Filter by status (DRAFT, PUBLISHED, ARCHIVED)
- `category` (string): Filter by category
- `featured` (boolean): Filter featured publications

### Get Publication by ID
```
GET /api/v1/publications/{id}
Authorization: Bearer {token}
```

### Admin: Create Publication
```
POST /api/v1/publications
Authorization: Bearer {token}
Middleware: role:admin,super_admin
```

**Request Body:**
```json
{
    "title": "Disaster Preparedness Guidelines",
    "content": "Comprehensive guide for disaster preparedness...",
    "category": "EDUCATION",
    "featured": true,
    "meta_description": "Learn how to prepare for natural disasters",
    "tags": ["preparedness", "safety", "education"],
    "publish_at": "2025-07-30T12:00:00Z"
}
```

---

## Forum Messages

### Get Report Messages
```
GET /api/v1/forum/reports/{reportId}/messages
Authorization: Bearer {token}
```

### Post Message to Report
```
POST /api/v1/forum/reports/{reportId}/messages
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "message": "I can confirm this flooding, I'm in the same area",
    "message_type": "WITNESS_CONFIRMATION",
    "attachments": [
        {
            "type": "image",
            "url": "/storage/forum/message_image.jpg"
        }
    ]
}
```

### Update Message
```
PUT /api/v1/forum/reports/{reportId}/messages/{messageId}
Authorization: Bearer {token}
```

### Delete Message
```
DELETE /api/v1/forum/reports/{reportId}/messages/{messageId}
Authorization: Bearer {token}
```

---

## Gibran Web Compatibility

### Get Berita Bencana (Public)
```
GET /api/gibran/berita-bencana
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "judul": "Banjir di Jakarta Pusat",
            "deskripsi": "Banjir besar melanda Jakarta Pusat...",
            "jenis_bencana": "BANJIR",
            "tingkat_keparahan": "TINGGI",
            "lokasi": "Jakarta, Indonesia",
            "tanggal_kejadian": "2025-07-30T08:00:00Z",
            "status": "TERVERIFIKASI"
        }
    ]
}
```

### Web Admin Login
```
POST /api/gibran/auth/login
```

### Get Pelaporans (Admin)
```
GET /api/gibran/pelaporans
Authorization: Bearer {token}
```

### Dashboard Statistics (Admin)
```
GET /api/gibran/dashboard/statistics
Authorization: Bearer {token}
```

---

## Error Handling

### Standard Error Response Format
```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field_name": ["Validation error message"]
    },
    "error_code": "VALIDATION_ERROR",
    "timestamp": "2025-07-30T10:30:00Z"
}
```

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error

### Error Codes
- `VALIDATION_ERROR` - Input validation failed
- `AUTHENTICATION_REQUIRED` - Missing or invalid token
- `INSUFFICIENT_PERMISSIONS` - User lacks required permissions
- `RESOURCE_NOT_FOUND` - Requested resource doesn't exist
- `FILE_UPLOAD_ERROR` - File upload failed
- `RATE_LIMIT_EXCEEDED` - Too many requests
- `PLATFORM_NOT_SUPPORTED` - Unsupported platform specified

---

## Rate Limiting

### Default Limits
- **General API**: 60 requests per minute per user
- **File Uploads**: 10 requests per minute per user
- **Notifications**: 100 requests per minute per user
- **Authentication**: 5 login attempts per minute per IP

### Rate Limit Headers
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1627646400
```

---

## Testing Endpoints

### Test Notification System
```
POST /api/test-notifications
```

**Response:**
```json
{
    "success": true,
    "message": "Cross-platform notification system test completed successfully",
    "test_results": {
        "volunteer_created": 1,
        "admin_created": 2,
        "test_report_created": 15,
        "volunteer_notifications": {
            "count": 3,
            "unread_count": 3,
            "platform": "mobile"
        },
        "admin_notifications": {
            "count": 2,
            "unread_count": 2,
            "platform": "web"
        }
    }
}
```

---

## Platform-Specific Notes

### Mobile App (Flutter)
- Use `platform=mobile` in requests where applicable
- FCM tokens required for push notifications
- Image uploads optimized for mobile data usage
- Offline-first approach with sync capabilities

### Web Dashboard (Gibran)
- Use `platform=web` in requests where applicable
- Enhanced admin features and bulk operations
- Real-time updates via WebSocket connections
- Desktop notification support

### Authentication Tokens
- Mobile: Long-lived tokens (30 days)
- Web: Session-based tokens (24 hours)
- Admin: Enhanced security with 2-hour expiry

---

## SDK and Integration Examples

### cURL Example
```bash
# Login
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123","platform":"mobile"}'

# Upload disaster image
curl -X POST https://your-domain.com/api/v1/files/disasters/1/images \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "images[]=@disaster_photo.jpg" \
  -F "platform=mobile"
```

### JavaScript/Axios Example
```javascript
// Set up axios instance
const api = axios.create({
  baseURL: 'https://your-domain.com/api/v1',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

// Get notifications
const notifications = await api.get('/notifications', {
  params: { platform: 'web', per_page: 20 }
});

// Upload file
const formData = new FormData();
formData.append('images[]', file);
formData.append('platform', 'web');

const uploadResponse = await api.post(`/files/disasters/${reportId}/images`, formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
});
```

### Flutter/Dart Example
```dart
// HTTP service setup
class ApiService {
  static const String baseUrl = 'https://your-domain.com/api/v1';
  
  static Future<Map<String, dynamic>> uploadDisasterImages(
    int reportId, 
    List<File> images
  ) async {
    var request = http.MultipartRequest(
      'POST', 
      Uri.parse('$baseUrl/files/disasters/$reportId/images')
    );
    
    request.headers['Authorization'] = 'Bearer $token';
    request.fields['platform'] = 'mobile';
    
    for (var image in images) {
      request.files.add(await http.MultipartFile.fromPath('images[]', image.path));
    }
    
    var response = await request.send();
    return json.decode(await response.stream.bytesToString());
  }
}
```

---

## Changelog

### Version 1.0.0 (2025-07-30)
- Initial cross-platform API release
- Unified authentication system
- Cross-platform notification service
- File upload system with optimization
- Disaster report management
- User management with role-based access
- Forum messaging system
- Gibran web compatibility layer
- Comprehensive error handling
- Rate limiting implementation

---

## Support and Contact

For API support, please contact:
- **Technical Support**: api-support@astacala-rescue.com
- **Documentation**: docs@astacala-rescue.com
- **Emergency Issues**: emergency@astacala-rescue.com

**API Status Page**: https://status.astacala-rescue.com  
**Postman Collection**: [Download Collection](https://your-domain.com/api/postman-collection.json)
