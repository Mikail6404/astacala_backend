<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DisasterReportController;
use App\Http\Controllers\API\ForumController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\PublicationController;
use App\Http\Controllers\API\CrossPlatformFileUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Version 1 Routes
Route::prefix('v1')->group(function () {

    // Health check endpoint (public)
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'Astacala Rescue API is running',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'platform_support' => ['mobile', 'web'],
            'integration_status' => 'cross-platform-ready'
        ]);
    });

    // Authentication routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Protected auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
        });
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

        // Disaster Reports (Unified for both mobile and web)
        Route::prefix('reports')->group(function () {
            Route::get('/', [DisasterReportController::class, 'index']);
            Route::post('/', [DisasterReportController::class, 'store']);
            Route::get('/statistics', [DisasterReportController::class, 'statistics']);
            Route::get('/{id}', [DisasterReportController::class, 'show']);
            Route::put('/{id}', [DisasterReportController::class, 'update']);
            Route::delete('/{id}', [DisasterReportController::class, 'destroy']);

            // Web-compatible endpoints
            Route::post('/web-submit', [DisasterReportController::class, 'webSubmit']);
            Route::get('/admin-view', [DisasterReportController::class, 'adminView']);
            Route::get('/pending', [DisasterReportController::class, 'pending']);
            Route::post('/{id}/verify', [DisasterReportController::class, 'verify']);
            Route::post('/{id}/publish', [DisasterReportController::class, 'publish']);

            // User-specific reports
            Route::get('/my-reports', [DisasterReportController::class, 'userReports']);
            Route::get('/my-statistics', [DisasterReportController::class, 'userStatistics']);
        });

        // User Management (Enhanced for cross-platform)
        Route::prefix('users')->group(function () {
            Route::get('/profile', [UserController::class, 'show']);
            Route::put('/profile', [UserController::class, 'update']);
            Route::post('/profile/avatar', [UserController::class, 'uploadAvatar']);
            Route::get('/reports', [DisasterReportController::class, 'userReports']);

            // Admin user management - MUST come before wildcard routes
            Route::middleware('role:admin,super_admin')->group(function () {
                Route::get('/admin-list', [UserController::class, 'adminList']);
                Route::get('/volunteer-list', [UserController::class, 'volunteerList']);
                Route::post('/create-admin', [UserController::class, 'createAdmin']);
                Route::get('/statistics', [UserController::class, 'statistics']);
                Route::put('/{id}/role', [UserController::class, 'updateRole']);
                Route::put('/{id}/status', [UserController::class, 'updateStatus']);
                Route::put('/{id}', [UserController::class, 'updateUserById']);
                Route::delete('/{id}', [UserController::class, 'deleteUserById']);
            });

            // Wildcard routes must come LAST
            Route::get('/{id}', [UserController::class, 'getUserById']);
        });

        // Publications (Web app integration)
        Route::prefix('publications')->group(function () {
            Route::get('/', [PublicationController::class, 'index']);
            Route::get('/{id}', [PublicationController::class, 'show']);

            // Admin operations
            Route::middleware('role:admin,super_admin')->group(function () {
                Route::post('/', [PublicationController::class, 'store']);
                Route::put('/{id}', [PublicationController::class, 'update']);
                Route::delete('/{id}', [PublicationController::class, 'destroy']);
                Route::post('/{id}/publish', [PublicationController::class, 'publish']);
            });
        });

        // Notifications (Enhanced Cross-Platform)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/mark-read', [NotificationController::class, 'markAsRead']);
            Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
            Route::post('/fcm-token', [NotificationController::class, 'updateFcmToken']);

            // Admin notification broadcasting
            Route::middleware('role:admin,super_admin')->group(function () {
                Route::post('/broadcast', [NotificationController::class, 'sendUrgentNotification']);
            });
        });

        // File Upload System (Cross-Platform)
        Route::prefix('files')->group(function () {
            // Image uploads for disaster reports
            Route::post('/disasters/{reportId}/images', [CrossPlatformFileUploadController::class, 'uploadDisasterImages']);
            Route::delete('/disasters/{reportId}/images/{imageId}', [CrossPlatformFileUploadController::class, 'deleteImage']);

            // Document uploads for disaster reports
            Route::post('/disasters/{reportId}/documents', [CrossPlatformFileUploadController::class, 'uploadDocument']);

            // User avatar upload - TEMPORARY FIX using basic upload
            Route::post('/avatar', [\App\Http\Controllers\API\BasicFileUploadController::class, 'uploadAvatar']);

            // Storage statistics (admin only)
            Route::middleware('role:admin,super_admin')->group(function () {
                Route::get('/storage/statistics', [CrossPlatformFileUploadController::class, 'getStorageStatistics']);
            });
        });

        // Forum Messages (Cross-platform communication)
        Route::prefix('forum')->group(function () {
            Route::get('/', [ForumController::class, 'index']);
            Route::post('/', [ForumController::class, 'store']);

            // Report-specific discussions
            Route::prefix('reports/{reportId}')->group(function () {
                Route::get('/messages', [ForumController::class, 'reportMessages']);
                Route::post('/messages', [ForumController::class, 'postMessage']);
                Route::put('/messages/{messageId}', [ForumController::class, 'updateMessage']);
                Route::delete('/messages/{messageId}', [ForumController::class, 'deleteMessage']);
                Route::post('/mark-read', [ForumController::class, 'markAsRead']);
            });
        });
    });
});

// Backward compatibility routes (existing mobile endpoints)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Protected routes (backward compatibility)
Route::middleware('auth:sanctum')->group(function () {

    // Disaster Reports (with both route formats for compatibility)
    Route::prefix('reports')->group(function () {
        Route::get('/', [DisasterReportController::class, 'index']);
        Route::post('/', [DisasterReportController::class, 'store']);
        Route::get('/statistics', [DisasterReportController::class, 'statistics']);
        Route::get('/{id}', [DisasterReportController::class, 'show']);
        Route::put('/{id}', [DisasterReportController::class, 'update']);
    });

    // Additional route for mobile compatibility
    Route::prefix('disasters')->group(function () {
        Route::prefix('reports')->group(function () {
            Route::get('/', [DisasterReportController::class, 'index']);
            Route::post('/', [DisasterReportController::class, 'store']);
            Route::get('/statistics', [DisasterReportController::class, 'statistics']);
            Route::get('/{id}', [DisasterReportController::class, 'show']);
            Route::put('/{id}', [DisasterReportController::class, 'update']);
        });
    });

    // User Profile Management
    Route::prefix('users')->group(function () {
        Route::get('/profile', [UserController::class, 'show']);
        Route::put('/profile', [UserController::class, 'update']);
        Route::post('/profile/avatar', [UserController::class, 'uploadAvatar']);
        Route::get('/reports', [DisasterReportController::class, 'userReports']);
        Route::get('/{id}', [UserController::class, 'getUserById']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/mark-read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // Forum Messages (Real-time communication for disaster reports)
    Route::prefix('disasters/{disaster_report_id}/forum')->group(function () {
        Route::get('/', [ForumController::class, 'index']);
        Route::post('/', [ForumController::class, 'store']);
        Route::get('/statistics', [ForumController::class, 'statistics']);
        Route::put('/{message_id}', [ForumController::class, 'update']);
        Route::delete('/{message_id}', [ForumController::class, 'destroy']);
        Route::post('/mark-read', [ForumController::class, 'markAsRead']);
    });
});

// ========================================
// GIBRAN WEB APP COMPATIBILITY ROUTES
// ========================================
use App\Http\Controllers\API\GibranWebCompatibilityController;

// Public routes for Gibran's web app
Route::prefix('gibran')->group(function () {

    // Public disaster news endpoint (maintains Gibran's existing API)
    Route::get('/berita-bencana', [GibranWebCompatibilityController::class, 'getBeritaBencana']);

    // Public publications endpoint for real publications
    Route::get('/publications', [GibranWebCompatibilityController::class, 'getPublications']);

    // Public pelaporans endpoint for read access (web dashboard needs this)
    Route::get('/pelaporans', [GibranWebCompatibilityController::class, 'getPelaporans']);

    // Authentication for Gibran's admin panel
    Route::post('/auth/login', [GibranWebCompatibilityController::class, 'webAuthLogin']);

    // Protected routes for Gibran's admin dashboard
    Route::middleware('auth:sanctum')->group(function () {

        // Disaster report management (pelaporans) - write operations only
        Route::prefix('pelaporans')->group(function () {
            Route::post('/', [GibranWebCompatibilityController::class, 'submitPelaporan']);
            Route::get('/{id}', [GibranWebCompatibilityController::class, 'showPelaporan']);
            Route::delete('/{id}', [GibranWebCompatibilityController::class, 'deletePelaporan']);
            Route::post('/{id}/verify', [GibranWebCompatibilityController::class, 'verifyPelaporan']);
        });

        // Dashboard statistics for admin panel
        Route::get('/dashboard/statistics', [GibranWebCompatibilityController::class, 'getDashboardStatistics']);

        // Notification management for web dashboard
        Route::prefix('notifikasi')->group(function () {
            Route::get('/{pengguna_id}', [GibranWebCompatibilityController::class, 'getUserNotifications']);
            Route::post('/send', [GibranWebCompatibilityController::class, 'sendNotification']);
        });
    });
});

// Legacy mobile compatibility (maintain existing mobile endpoints)
Route::prefix('pelaporans')->group(function () {
    Route::post('/', [GibranWebCompatibilityController::class, 'submitPelaporan'])->middleware('auth:sanctum');
});

// Test notification endpoint
Route::post('/test-notifications', function () {

    try {
        $notificationService = app(App\Services\CrossPlatformNotificationService::class);

        // Test 1: Create a test volunteer user
        $volunteer = App\Models\User::firstOrCreate(
            ['email' => 'test.volunteer@example.com'],
            [
                'name' => 'Test Volunteer',
                'password' => bcrypt('password'),
                'role' => 'VOLUNTEER',
                'phone' => '1234567890',
                'is_active' => true,
                'email_verified_at' => now()
            ]
        );

        // Test 2: Create a test admin user
        $admin = App\Models\User::firstOrCreate(
            ['email' => 'test.admin@example.com'],
            [
                'name' => 'Test Admin',
                'password' => bcrypt('password'),
                'role' => 'ADMIN',
                'phone' => '1234567891',
                'is_active' => true,
                'email_verified_at' => now()
            ]
        );

        // Test 3: Create a test disaster report
        $report = App\Models\DisasterReport::create([
            'title' => 'Test Notification Report',
            'description' => 'This is a test report for notification system verification',
            'disaster_type' => 'FLOOD',
            'severity_level' => 'MEDIUM',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'location_name' => 'Jakarta, Indonesia',
            'estimated_affected' => 100,
            'incident_timestamp' => now(),
            'reported_by' => $volunteer->id,
            'status' => 'PENDING'
        ]);

        // Test 4: Send new report notification to admins
        $notificationService->notifyNewReportToAdmins($report);

        // Test 5: Simulate report verification
        $report->update(['status' => 'VERIFIED']);
        $notificationService->notifyReportVerified($report);

        // Test 6: Send urgent notification
        $notificationService->sendUrgentNotification(
            'System Test Alert',
            'This is a test of the urgent notification system.',
            ['priority' => 'HIGH', 'test' => true]
        );

        // Test 7: Get notifications for volunteer (mobile)
        $volunteerNotifications = $notificationService->getPlatformNotifications($volunteer, 'mobile');
        $volunteerUnreadCount = $notificationService->getUnreadCount($volunteer, 'mobile');

        // Test 8: Get notifications for admin (web)
        $adminNotifications = $notificationService->getPlatformNotifications($admin, 'web');
        $adminUnreadCount = $notificationService->getUnreadCount($admin, 'web');

        return response()->json([
            'success' => true,
            'message' => 'Cross-platform notification system test completed successfully',
            'test_results' => [
                'volunteer_created' => $volunteer->id,
                'admin_created' => $admin->id,
                'test_report_created' => $report->id,
                'volunteer_notifications' => [
                    'count' => count($volunteerNotifications),
                    'unread_count' => $volunteerUnreadCount,
                    'platform' => 'mobile',
                    'notifications' => array_slice($volunteerNotifications, 0, 3) // Show first 3
                ],
                'admin_notifications' => [
                    'count' => count($adminNotifications),
                    'unread_count' => $adminUnreadCount,
                    'platform' => 'web',
                    'notifications' => array_slice($adminNotifications, 0, 3) // Show first 3
                ]
            ],
            'next_steps' => [
                'mobile_app' => 'Use GET /api/v1/notifications?platform=mobile to fetch mobile notifications',
                'web_dashboard' => 'Use GET /api/v1/notifications?platform=web to fetch web notifications',
                'mark_read' => 'Use POST /api/v1/notifications/mark-read with notification IDs',
                'fcm_token' => 'Use POST /api/v1/notifications/fcm-token to register push notification token'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Notification system test failed',
            'error' => $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode to see trace'
        ], 500);
    }
});

// WebSocket events test endpoint
Route::post('/test-websocket-events', function () {
    try {
        $results = [];

        // Test 1: Broadcast Disaster Report Submitted Event
        $testReportData = [
            'id' => 999,
            'type' => 'Banjir',
            'location' => 'Jakarta Pusat',
            'reporter_name' => 'Test Reporter',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'timestamp' => now()->toISOString(),
            'platform' => 'web',
            'test_event' => true
        ];

        event(new \App\Events\DisasterReportSubmitted($testReportData));
        $results[] = 'DisasterReportSubmitted event broadcasted';

        // Test 2: Broadcast Report Verified Event
        $testVerificationData = [
            'report_id' => 999,
            'verified_by' => 'Test Admin',
            'verification_status' => 'VERIFIED',
            'timestamp' => now()->toISOString(),
            'platform' => 'web',
            'test_event' => true
        ];

        event(new \App\Events\ReportVerified($testVerificationData));
        $results[] = 'ReportVerified event broadcasted';

        // Test 3: Broadcast Admin Notification Event
        $testAdminType = 'HIGH_PRIORITY_REPORT';
        $testAdminTitle = 'High Priority Alert';
        $testAdminMessage = 'Test high priority disaster report submitted';
        $testAdminData = [
            'report_data' => $testReportData,
            'timestamp' => now()->toISOString(),
            'platform' => 'web',
            'test_event' => true
        ];

        event(new \App\Events\AdminNotification($testAdminType, $testAdminTitle, $testAdminMessage, $testAdminData));
        $results[] = 'AdminNotification event broadcasted';

        return response()->json([
            'success' => true,
            'message' => 'WebSocket test events broadcasted successfully',
            'events_sent' => count($results),
            'results' => $results,
            'websocket_server' => 'Laravel Reverb on port 8080',
            'channels' => [
                'general-notifications' => 'All general events',
                'admin-notifications' => 'Admin-specific events'
            ],
            'test_data' => [
                'disaster_report' => $testReportData,
                'verification' => $testVerificationData,
                'admin_notification' => [
                    'type' => $testAdminType,
                    'title' => $testAdminTitle,
                    'message' => $testAdminMessage,
                    'data' => $testAdminData
                ]
            ],
            'timestamp' => now()->toISOString()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'WebSocket test failed',
            'error' => $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode to see trace'
        ], 500);
    }
});
