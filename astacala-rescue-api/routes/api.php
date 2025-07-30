<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DisasterReportController;
use App\Http\Controllers\API\ForumController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\PublicationController;
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

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    
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
            Route::get('/{id}', [UserController::class, 'getUserById']);
            
            // Admin user management
            Route::middleware('role:admin,super_admin')->group(function () {
                Route::get('/admin-list', [UserController::class, 'adminList']);
                Route::post('/create-admin', [UserController::class, 'createAdmin']);
                Route::put('/{id}/role', [UserController::class, 'updateRole']);
                Route::put('/{id}/status', [UserController::class, 'updateStatus']);
                Route::get('/statistics', [UserController::class, 'statistics']);
            });
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

        // Notifications (Enhanced)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/mark-read', [NotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            
            // Admin notification broadcasting
            Route::middleware('role:admin,super_admin')->group(function () {
                Route::post('/broadcast', [NotificationController::class, 'broadcast']);
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
