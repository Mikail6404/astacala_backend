# API Testing Guide

## Overview

This guide provides comprehensive testing instructions for the Astacala Rescue Cross-Platform API. It includes manual testing procedures, automated testing setups, and integration testing workflows for both mobile and web platforms.

## Table of Contents

1. [Testing Environment Setup](#testing-environment-setup)
2. [Authentication Testing](#authentication-testing)
3. [Disaster Reports Testing](#disaster-reports-testing)
4. [File Upload Testing](#file-upload-testing)
5. [Notification System Testing](#notification-system-testing)
6. [Cross-Platform Integration Testing](#cross-platform-integration-testing)
7. [Performance Testing](#performance-testing)
8. [Security Testing](#security-testing)
9. [Error Handling Testing](#error-handling-testing)
10. [Automated Testing Setup](#automated-testing-setup)

---

## Testing Environment Setup

### Prerequisites
- Postman or Insomnia REST client
- Test user accounts with different roles
- Sample files for upload testing
- FCM server key for push notification testing

### Environment Variables
Create the following environment variables in your testing tool:

```json
{
  "base_url": "http://localhost:8000/api",
  "admin_email": "admin@test.com",
  "admin_password": "admin123",
  "volunteer_email": "volunteer@test.com",
  "volunteer_password": "volunteer123",
  "bearer_token": "",
  "report_id": "",
  "user_id": ""
}
```

### Test Data Setup
Run this setup script to create test data:

```bash
# Run Laravel seeders
php artisan db:seed --class=TestUserSeeder
php artisan db:seed --class=TestDisasterReportSeeder

# Or use the API endpoint
POST /api/test-notifications
```

---

## Authentication Testing

### Test Case 1: User Registration
**Objective**: Verify user registration functionality

**Steps**:
1. Send POST request to `/api/v1/auth/register`
2. Use valid registration data
3. Verify response contains user data and token
4. Verify user is created in database

**Expected Result**:
- Status: 201 Created
- Response includes user object and auth token
- Token is valid for subsequent requests

**Test Data**:
```json
{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "+628123456789",
    "role": "VOLUNTEER"
}
```

### Test Case 2: User Login
**Objective**: Verify login functionality for both platforms

**Steps**:
1. Send POST request to `/api/v1/auth/login`
2. Test with mobile and web platforms
3. Verify token generation and expiry
4. Test invalid credentials

**Mobile Login Test**:
```json
{
    "email": "test@example.com",
    "password": "password123",
    "platform": "mobile"
}
```

**Web Login Test**:
```json
{
    "email": "admin@example.com",
    "password": "admin123",
    "platform": "web"
}
```

### Test Case 3: Token Validation
**Objective**: Verify token-based authentication

**Steps**:
1. Login to get token
2. Use token in Authorization header
3. Access protected endpoints
4. Test expired token handling

---

## Disaster Reports Testing

### Test Case 4: Create Disaster Report
**Objective**: Verify disaster report creation

**Steps**:
1. Authenticate user
2. Send POST request to `/api/v1/reports`
3. Verify report creation
4. Check database entry

**Test Data**:
```json
{
    "title": "Test Flood Report",
    "description": "Heavy flooding in test area",
    "disaster_type": "FLOOD",
    "severity_level": "HIGH",
    "latitude": -6.2088,
    "longitude": 106.8456,
    "location_name": "Jakarta, Indonesia",
    "estimated_affected": 500,
    "incident_timestamp": "2025-07-30T10:00:00Z",
    "platform": "mobile"
}
```

### Test Case 5: List Reports with Filters
**Objective**: Verify report listing and filtering

**Filter Tests**:
```bash
# By status
GET /api/v1/reports?status=PENDING

# By disaster type
GET /api/v1/reports?disaster_type=FLOOD

# By platform
GET /api/v1/reports?platform=mobile

# Pagination
GET /api/v1/reports?page=2&per_page=10
```

### Test Case 6: Report Statistics
**Objective**: Verify statistics endpoint

**Steps**:
1. Get statistics: `GET /api/v1/reports/statistics`
2. Verify data structure
3. Check calculation accuracy
4. Test admin vs user access

---

## File Upload Testing

### Test Case 7: Image Upload
**Objective**: Verify image upload functionality

**Setup**:
- Prepare test images (JPEG, PNG, WebP)
- Various file sizes (under and over 10MB)
- Invalid file types for negative testing

**Steps**:
1. Create disaster report
2. Upload images: `POST /api/v1/files/disasters/{reportId}/images`
3. Verify image optimization
4. Check thumbnail generation
5. Verify metadata extraction

**cURL Example**:
```bash
curl -X POST http://localhost:8000/api/v1/files/disasters/1/images \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "images[]=@test_image.jpg" \
  -F "platform=mobile" \
  -F "is_primary=true"
```

### Test Case 8: File Upload Limits
**Objective**: Verify file size and type restrictions

**Tests**:
- Upload file over 10MB (should fail)
- Upload unsupported format (should fail)
- Upload 10 images at once
- Upload 11 images at once (should fail)

### Test Case 9: Storage Statistics
**Objective**: Verify storage statistics (admin only)

**Steps**:
1. Login as admin
2. Get statistics: `GET /api/v1/files/storage/statistics`
3. Verify access control for non-admin users

---

## Notification System Testing

### Test Case 10: FCM Token Registration
**Objective**: Verify push notification token registration

**Steps**:
1. Register FCM token: `POST /api/v1/notifications/fcm-token`
2. Verify token storage
3. Test platform-specific tokens

**Test Data**:
```json
{
    "fcm_token": "test_fcm_token_12345",
    "platform": "mobile",
    "device_info": {
        "device_type": "android",
        "app_version": "1.0.0",
        "os_version": "Android 13"
    }
}
```

### Test Case 11: Notification Delivery
**Objective**: Verify notification creation and delivery

**Steps**:
1. Create disaster report (triggers notification)
2. Check admin notifications
3. Verify platform-specific delivery
4. Test urgent broadcast

**Admin Broadcast Test**:
```json
{
    "title": "Test Emergency Alert",
    "message": "This is a test emergency notification",
    "type": "EMERGENCY_ALERT",
    "target_platforms": ["mobile", "web"],
    "target_roles": ["VOLUNTEER", "ADMIN"]
}
```

### Test Case 12: Notification Management
**Objective**: Verify notification CRUD operations

**Steps**:
1. Get notifications: `GET /api/v1/notifications?platform=mobile`
2. Get unread count: `GET /api/v1/notifications/unread-count`
3. Mark as read: `POST /api/v1/notifications/mark-read`
4. Delete notification: `DELETE /api/v1/notifications/{id}`

---

## Cross-Platform Integration Testing

### Test Case 13: Mobile-Web Data Consistency
**Objective**: Verify data consistency between platforms

**Steps**:
1. Create report via mobile API
2. Verify data appears in web API
3. Update via web API
4. Verify changes in mobile API

### Test Case 14: Platform-Specific Features
**Objective**: Test platform-specific functionality

**Mobile Tests**:
- FCM push notifications
- Mobile-optimized image uploads
- Location-based features

**Web Tests**:
- Admin dashboard endpoints
- Bulk operations
- Advanced filtering

### Test Case 15: Gibran Web Compatibility
**Objective**: Verify Gibran web app integration

**Steps**:
1. Test public endpoint: `GET /api/gibran/berita-bencana`
2. Test admin login: `POST /api/gibran/auth/login`
3. Test dashboard statistics: `GET /api/gibran/dashboard/statistics`

---

## Performance Testing

### Test Case 16: Load Testing
**Objective**: Verify API performance under load

**Tools**: Apache Bench (ab) or Artillery.js

**Tests**:
```bash
# Test authentication endpoint
ab -n 1000 -c 10 -H "Content-Type: application/json" \
   -p login_data.json http://localhost:8000/api/v1/auth/login

# Test report listing
ab -n 1000 -c 10 -H "Authorization: Bearer TOKEN" \
   http://localhost:8000/api/v1/reports
```

### Test Case 17: File Upload Performance
**Objective**: Verify file upload performance

**Tests**:
- Upload multiple large images simultaneously
- Measure processing time for optimization
- Test concurrent uploads

---

## Security Testing

### Test Case 18: Authentication Security
**Objective**: Verify authentication security

**Tests**:
- SQL injection in login fields
- XSS in user input fields
- Token tampering
- Rate limiting verification

### Test Case 19: Authorization Testing
**Objective**: Verify role-based access control

**Tests**:
- Volunteer accessing admin endpoints (should fail)
- Unauthenticated access to protected endpoints
- Cross-user data access attempts

### Test Case 20: File Upload Security
**Objective**: Verify file upload security

**Tests**:
- Upload malicious files (PHP, executable)
- Upload files with script injection
- Path traversal attempts
- MIME type spoofing

---

## Error Handling Testing

### Test Case 21: Validation Errors
**Objective**: Verify proper error handling

**Tests**:
```json
// Invalid email format
{
    "email": "invalid-email",
    "password": "test"
}

// Missing required fields
{
    "title": "Test Report"
    // Missing required fields
}
```

### Test Case 22: HTTP Status Codes
**Objective**: Verify correct HTTP status codes

**Expected Codes**:
- 200: Successful GET/PUT
- 201: Successful POST (creation)
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 500: Server Error

---

## Automated Testing Setup

### PHPUnit Tests
Create automated tests for Laravel backend:

```php
<?php
// tests/Feature/DisasterReportTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DisasterReport;

class DisasterReportTest extends TestCase
{
    public function test_can_create_disaster_report()
    {
        $user = User::factory()->create(['role' => 'VOLUNTEER']);
        
        $response = $this->actingAs($user)
            ->postJson('/api/v1/reports', [
                'title' => 'Test Report',
                'description' => 'Test Description',
                'disaster_type' => 'FLOOD',
                'severity_level' => 'HIGH',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'location_name' => 'Jakarta',
                'estimated_affected' => 100,
                'incident_timestamp' => now()->toISOString(),
                'platform' => 'mobile'
            ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'title',
                        'description',
                        'disaster_type'
                    ]
                ]);
    }
    
    public function test_can_upload_disaster_images()
    {
        $user = User::factory()->create();
        $report = DisasterReport::factory()->create(['reported_by' => $user->id]);
        
        $file = UploadedFile::fake()->image('test.jpg', 1920, 1080);
        
        $response = $this->actingAs($user)
            ->post("/api/v1/files/disasters/{$report->id}/images", [
                'images' => [$file],
                'platform' => 'mobile'
            ]);
            
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'uploaded_images' => [
                            '*' => [
                                'id',
                                'original_filename',
                                'url',
                                'thumbnail_url'
                            ]
                        ]
                    ]
                ]);
    }
}
```

### Run Tests
```bash
# Run all tests
php artisan test

# Run specific test class
php artisan test tests/Feature/DisasterReportTest.php

# Run with coverage
php artisan test --coverage
```

### Postman Automated Tests
Use Postman's test scripts for API testing:

```javascript
// Test script example for login endpoint
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has token", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.data).to.have.property('token');
    pm.collectionVariables.set("bearer_token", jsonData.data.token);
});

pm.test("User role is correct", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.data.user.role).to.eql("VOLUNTEER");
});
```

---

## Test Scenarios Checklist

### Authentication & Authorization ✓
- [ ] User registration (mobile/web)
- [ ] User login (mobile/web)
- [ ] Token validation
- [ ] Password change
- [ ] Logout functionality
- [ ] Role-based access control

### Disaster Reports ✓
- [ ] Create report (mobile/web)
- [ ] List reports with filters
- [ ] Update report
- [ ] Delete report
- [ ] Report statistics
- [ ] Admin verification
- [ ] Admin publishing

### File Management ✓
- [ ] Image upload (single/multiple)
- [ ] Image optimization
- [ ] Thumbnail generation
- [ ] Avatar upload
- [ ] Document upload
- [ ] File deletion
- [ ] Storage statistics

### Notifications ✓
- [ ] FCM token registration
- [ ] Notification creation
- [ ] Platform-specific delivery
- [ ] Mark as read
- [ ] Unread count
- [ ] Admin broadcast

### Cross-Platform ✓
- [ ] Data consistency
- [ ] Platform-specific features
- [ ] Gibran compatibility
- [ ] Mobile optimization
- [ ] Web admin features

### Performance ✓
- [ ] Load testing
- [ ] File upload performance
- [ ] Database query optimization
- [ ] Response time verification

### Security ✓
- [ ] Input validation
- [ ] SQL injection protection
- [ ] XSS prevention
- [ ] File upload security
- [ ] Rate limiting

---

## Reporting Issues

When reporting issues, include:

1. **Test Case ID**: Reference specific test case
2. **Environment**: Local/staging/production
3. **Platform**: Mobile/web
4. **Steps to Reproduce**: Detailed steps
5. **Expected Result**: What should happen
6. **Actual Result**: What actually happened
7. **Screenshots/Logs**: Supporting evidence

### Issue Template
```markdown
## Bug Report

**Test Case**: Test Case 7 - Image Upload
**Environment**: Local development
**Platform**: Mobile API
**Severity**: High

**Steps to Reproduce**:
1. Login as volunteer user
2. Create disaster report
3. Upload 5MB JPEG image
4. Check response

**Expected Result**: 
Image should be uploaded, optimized, and thumbnail generated

**Actual Result**: 
Server returns 500 error, image not processed

**Error Message**: 
"GD Library extension not found"

**Supporting Files**: 
- error.log
- screenshot.png
```

---

## Continuous Integration

### GitHub Actions Workflow
```yaml
name: API Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, dom, fileinfo, mysql, gd
        
    - name: Install dependencies
      run: composer install
      
    - name: Run tests
      run: php artisan test --coverage
      
    - name: Run Postman tests
      uses: matt-ball/newman-action@master
      with:
        collection: postman-collection.json
        environment: test-environment.json
```

This comprehensive testing guide ensures thorough validation of all API functionality across both mobile and web platforms.
