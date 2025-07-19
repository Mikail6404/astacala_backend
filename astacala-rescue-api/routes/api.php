<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DisasterReportController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\UserController;
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

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Disaster Reports
    Route::prefix('reports')->group(function () {
        Route::get('/', [DisasterReportController::class, 'index']);
        Route::post('/', [DisasterReportController::class, 'store']);
        Route::get('/statistics', [DisasterReportController::class, 'statistics']);
        Route::get('/{id}', [DisasterReportController::class, 'show']);
        Route::put('/{id}', [DisasterReportController::class, 'update']);
    });

    // User Profile Management
    Route::prefix('users')->group(function () {
        Route::get('/profile', [UserController::class, 'show']);
        Route::put('/profile', [UserController::class, 'update']);
        Route::post('/profile/avatar', [UserController::class, 'uploadAvatar']);
        Route::get('/{id}', [UserController::class, 'getUserById']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/mark-read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
});
