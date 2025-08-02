# Astacala Rescue API

A comprehensive cross-platform RESTful API for disaster reporting and management, supporting both mobile (Flutter) and web (Gibran dashboard) applications.

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- MySQL/PostgreSQL
- Laravel 11.x
- GD Extension (for image processing)
- Laravel Reverb (for WebSocket support)

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/your-repo/astacala-rescue-api.git
cd astacala-rescue-api
```

2. **Install dependencies**
```bash
composer install
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure database**
```bash
# Edit .env file with your database credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=astacala_rescue
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

5. **Run migrations and seeders**
```bash
php artisan migrate
php artisan db:seed
```

6. **Install image processing package**
```bash
composer require intervention/image
```

7. **Create storage links**
```bash
php artisan storage:link
```

8. **Start the server**
```bash
php artisan serve
```

The API will be available at `http://localhost:8000/api`

## 📱 Platform Support

### Mobile App (Flutter)
- Cross-platform disaster reporting
- Real-time notifications via FCM
- Image upload with optimization
- Offline-first architecture support

### Web Dashboard (Gibran)
- Admin panel for report management
- Advanced analytics and statistics
- Bulk operations and user management
- Real-time monitoring dashboard

## 🔑 Authentication

The API uses Laravel Sanctum for authentication:

```bash
# Register a new user
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "+628123456789",
    "role": "VOLUNTEER"
  }'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123",
    "platform": "mobile"
  }'
```

## 📊 API Endpoints Overview

### Core Endpoints
- **Health Check**: `GET /api/health`
- **Authentication**: `POST /api/v1/auth/*`
- **Disaster Reports**: `GET|POST|PUT|DELETE /api/v1/reports/*`
- **File Upload**: `POST /api/v1/files/*`
- **Notifications**: `GET|POST /api/v1/notifications/*`
- **User Management**: `GET|PUT /api/v1/users/*`

### Admin Endpoints
- **Report Management**: `POST /api/v1/reports/{id}/verify`
- **User Administration**: `GET /api/v1/users/admin-list`
- **Storage Statistics**: `GET /api/v1/files/storage/statistics`
- **Broadcast Notifications**: `POST /api/v1/notifications/broadcast`

### Gibran Web Compatibility
- **Public News**: `GET /api/gibran/berita-bencana`
- **Admin Dashboard**: `GET /api/gibran/dashboard/statistics`
- **Web Login**: `POST /api/gibran/auth/login`

## 🧪 Testing

### Run API Tests
```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test class
php artisan test tests/Feature/DisasterReportTest.php
```

### Quick API Test
```bash
# Test the notification system
curl -X POST http://localhost:8000/api/test-notifications
```

### Import Postman Collection
1. Import `postman-collection.json` into Postman
2. Set environment variables (base_url, tokens)
3. Run the collection tests

## 📁 File Upload Features

### Supported File Types
- **Images**: JPEG, PNG, WebP (max 10MB)
- **Documents**: PDF, DOC, DOCX, TXT (max 20MB)

### Automatic Processing
- Image optimization (max 1920x1080)
- Thumbnail generation (300x200)
- EXIF data extraction
- Security scanning

### Upload Example
```bash
curl -X POST http://localhost:8000/api/v1/files/disasters/1/images \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "images[]=@photo.jpg" \
  -F "platform=mobile"
```

## 🔔 Notification System

### Push Notifications (FCM)
```bash
# Register FCM token
curl -X POST http://localhost:8000/api/v1/notifications/fcm-token \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "fcm_token": "your_fcm_token",
    "platform": "mobile"
  }'
```

### Notification Types
- **DISASTER_ALERT**: New high-priority reports
- **REPORT_UPDATE**: Status changes and verifications
- **SYSTEM_MESSAGE**: General system notifications
- **EMERGENCY_ALERT**: Urgent broadcasts from admins

## 🛠 Configuration

### Environment Variables
```bash
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=astacala_rescue

# File Storage
FILESYSTEM_DISK=local
INTERVENTION_IMAGE_DRIVER=gd

# Push Notifications
FCM_SERVER_KEY=your_fcm_server_key
FCM_SENDER_ID=your_sender_id

# Cross-Platform Settings
MOBILE_TOKEN_EXPIRY=43200  # 30 days in minutes
WEB_TOKEN_EXPIRY=1440      # 24 hours in minutes
```

### Storage Configuration
```php
// config/filesystems.php
'disks' => [
    'disaster_images' => [
        'driver' => 'local',
        'root' => storage_path('app/public/disasters'),
        'url' => env('APP_URL').'/storage/disasters',
    ],
    'user_avatars' => [
        'driver' => 'local',
        'root' => storage_path('app/public/avatars'),
        'url' => env('APP_URL').'/storage/avatars',
    ]
]
```

## 📈 Performance Optimization

### Database Indexing
```sql
-- Key indexes for performance
CREATE INDEX idx_disaster_reports_location ON disaster_reports(latitude, longitude);
CREATE INDEX idx_disaster_reports_status ON disaster_reports(status);
CREATE INDEX idx_disaster_reports_type ON disaster_reports(disaster_type);
CREATE INDEX idx_notifications_user_platform ON notifications(user_id, platform, is_read);
```

### Caching Strategy
```php
// Cache configuration
'stores' => [
    'reports' => [
        'driver' => 'redis',
        'ttl' => 3600, // 1 hour
    ],
    'statistics' => [
        'driver' => 'redis',
        'ttl' => 7200, // 2 hours
    ]
]
```

## 🔧 Development

### Project Structure
```
app/
├── Http/Controllers/API/          # API Controllers
├── Services/                      # Business Logic Services
├── Models/                        # Eloquent Models
├── Notifications/                 # Notification Classes
└── Jobs/                         # Background Jobs

database/
├── migrations/                    # Database Migrations
├── seeders/                      # Test Data Seeders
└── factories/                    # Model Factories

tests/
├── Feature/                      # Feature Tests
└── Unit/                        # Unit Tests
```

### Key Services
- **CrossPlatformNotificationService**: Handles all notification logic
- **CrossPlatformFileStorageService**: Manages file uploads and processing
- **DisasterReportService**: Business logic for disaster reports
- **UserManagementService**: User-related operations

### Adding New Features

1. **Create Migration**
```bash
php artisan make:migration create_new_feature_table
```

2. **Create Model**
```bash
php artisan make:model NewFeature
```

3. **Create Controller**
```bash
php artisan make:controller API/NewFeatureController --api
```

4. **Add Routes**
```php
// routes/api.php
Route::apiResource('new-features', NewFeatureController::class);
```

5. **Write Tests**
```bash
php artisan make:test NewFeatureTest
```

## 🚀 Deployment

### Production Checklist
- [ ] Environment variables configured
- [ ] Database migrations run
- [ ] File storage permissions set
- [ ] SSL certificate installed
- [ ] Rate limiting configured
- [ ] Monitoring setup
- [ ] Backup strategy implemented

### Docker Deployment
```dockerfile
FROM php:8.1-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl libpng-dev libjpeg-dev libfreetype6-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql gd

# Copy application
COPY . /var/www/html
WORKDIR /var/www/html

# Install Composer dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
```

### Production Environment
```bash
# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Set permissions
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

## 🔍 Monitoring

### Health Checks
```bash
# API health check
curl http://your-domain.com/api/health

# Database health
php artisan health:database

# Storage health
php artisan health:storage
```

### Logging
```php
// config/logging.php
'channels' => [
    'api' => [
        'driver' => 'daily',
        'path' => storage_path('logs/api.log'),
        'level' => 'info',
        'days' => 14,
    ]
]
```

## 📚 Documentation

- **[Complete API Documentation](API_DOCUMENTATION.md)** - Detailed endpoint documentation
- **[Testing Guide](API_TESTING_GUIDE.md)** - Comprehensive testing procedures
- **[Postman Collection](postman-collection.json)** - Ready-to-use API collection

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/new-feature`
3. Make your changes
4. Run tests: `php artisan test`
5. Commit changes: `git commit -am 'Add new feature'`
6. Push to branch: `git push origin feature/new-feature`
7. Submit a pull request

### Code Standards
- Follow PSR-12 coding standards
- Write comprehensive tests
- Update documentation
- Use meaningful commit messages

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [API_DOCUMENTATION.md](API_DOCUMENTATION.md)
- **Issues**: [GitHub Issues](https://github.com/your-repo/issues)
- **Email**: api-support@astacala-rescue.com

## 🎯 Roadmap

### Current Version (1.0.0)
- ✅ Cross-platform API
- ✅ File upload system
- ✅ Push notifications
- ✅ Admin dashboard integration

### Upcoming Features (1.1.0)
- [ ] Real-time WebSocket connections
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] AI-powered disaster classification
- [ ] Integration with external weather APIs
- [ ] Mobile offline sync capabilities

### Future Enhancements (2.0.0)
- [ ] Microservices architecture
- [ ] GraphQL API
- [ ] Machine learning predictions
- [ ] Blockchain verification system
- [ ] IoT sensor integration
- [ ] Advanced mapping features

---

**Built with ❤️ for disaster management and community safety**
