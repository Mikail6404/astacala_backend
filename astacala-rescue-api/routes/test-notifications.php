<?php

use App\Models\DisasterReport;
use App\Models\User;
use App\Services\CrossPlatformNotificationService;
use Illuminate\Support\Facades\Route;

/**
 * Test endpoint for notification system
 * POST /test-notifications
 */
Route::post('/test-notifications', function () {

    try {
        $notificationService = app(CrossPlatformNotificationService::class);

        // Test 1: Create a test volunteer user
        $volunteer = User::firstOrCreate(
            ['email' => 'test.volunteer@example.com'],
            [
                'name' => 'Test Volunteer',
                'password' => bcrypt('password'),
                'role' => 'VOLUNTEER',
                'phone' => '1234567890',
                'is_active' => true,
                'email_verified' => true,
            ]
        );

        // Test 2: Create a test admin user
        $admin = User::firstOrCreate(
            ['email' => 'test.admin@example.com'],
            [
                'name' => 'Test Admin',
                'password' => bcrypt('password'),
                'role' => 'ADMIN',
                'phone' => '1234567891',
                'is_active' => true,
                'email_verified' => true,
            ]
        );

        // Test 3: Create a test disaster report
        $report = DisasterReport::create([
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
            'status' => 'PENDING',
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
                    'notifications' => array_slice($volunteerNotifications, 0, 3), // Show first 3
                ],
                'admin_notifications' => [
                    'count' => count($adminNotifications),
                    'unread_count' => $adminUnreadCount,
                    'platform' => 'web',
                    'notifications' => array_slice($adminNotifications, 0, 3), // Show first 3
                ],
            ],
            'next_steps' => [
                'mobile_app' => 'Use GET /api/v1/notifications?platform=mobile to fetch mobile notifications',
                'web_dashboard' => 'Use GET /api/v1/notifications?platform=web to fetch web notifications',
                'mark_read' => 'Use POST /api/v1/notifications/mark-read with notification IDs',
                'fcm_token' => 'Use POST /api/v1/notifications/fcm-token to register push notification token',
            ],
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Notification system test failed',
            'error' => $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode to see trace',
        ], 500);
    }
});
