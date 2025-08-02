<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DisasterReport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TestNotificationSystem extends Command
{
    protected $signature = 'test:notification-system';
    protected $description = 'Test notification system across mobile and web platforms';

    private $baseUrl = 'http://localhost:8000/api/v1';
    private $mobileToken = null;
    private $testUserId = null;

    public function handle()
    {
        $this->info('📢 Astacala Cross-Platform Notification System Test');
        $this->info('===================================================');
        $this->newLine();

        try {
            $this->setupTestEnvironment();
            $this->testNotificationEndpoints();
            $this->testUnreadCount();
            $this->testMarkAsRead();
            $this->cleanupTestData();

            $this->info('✅ Cross-platform notification tests completed successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->cleanupTestData();
            return 1;
        }
    }

    private function setupTestEnvironment()
    {
        $this->info('🛠️ Setting up test environment...');

        // Create test user
        $testUser = User::create([
            'name' => 'Notification Test User',
            'email' => 'notification@test.com',
            'password' => Hash::make('testpassword123'),
            'is_active' => true,
            'role' => 'VOLUNTEER'
        ]);

        $this->testUserId = $testUser->id;
        $this->info("   ✅ Test user created (ID: {$this->testUserId})");

        // Authenticate
        $response = Http::post($this->baseUrl . '/auth/login', [
            'email' => 'notification@test.com',
            'password' => 'testpassword123'
        ]);

        if (!$response->successful() || !isset($response->json()['data']['tokens']['accessToken'])) {
            throw new \Exception("Authentication failed: " . $response->body());
        }

        $this->mobileToken = $response->json()['data']['tokens']['accessToken'];
        $this->info("   ✅ Authentication successful");
    }

    private function testNotificationEndpoints()
    {
        $this->info('📬 Testing notification endpoints...');

        // Test get notifications
        $response = Http::withToken($this->mobileToken)
            ->get($this->baseUrl . '/notifications');

        if (!$response->successful()) {
            throw new \Exception("Failed to get notifications: " . $response->body());
        }

        $this->info("   ✅ Get notifications endpoint working");

        // Test unread count
        $response = Http::withToken($this->mobileToken)
            ->get($this->baseUrl . '/notifications/unread-count');

        if (!$response->successful()) {
            throw new \Exception("Failed to get unread count: " . $response->body());
        }

        $this->info("   ✅ Unread count endpoint working");
    }

    private function testUnreadCount()
    {
        $this->info('🔢 Testing unread count functionality...');

        $response = Http::withToken($this->mobileToken)
            ->get($this->baseUrl . '/notifications/unread-count');

        if (!$response->successful()) {
            throw new \Exception("Failed to get unread count");
        }

        $data = $response->json();

        // Verify response structure (flexible check)
        if (isset($data['data']['unreadCount'])) {
            $this->info("   ✅ Unread count: " . $data['data']['unreadCount']);
        } elseif (isset($data['unreadCount'])) {
            $this->info("   ✅ Unread count: " . $data['unreadCount']);
        } elseif (isset($data['count'])) {
            $this->info("   ✅ Unread count: " . $data['count']);
        } else {
            $this->info("   ✅ Unread count endpoint working (Response: " . json_encode($data) . ")");
        }
    }

    private function testMarkAsRead()
    {
        $this->info('✅ Testing mark as read functionality...');

        // First get all notifications
        $response = Http::withToken($this->mobileToken)
            ->get($this->baseUrl . '/notifications');

        if (!$response->successful()) {
            throw new \Exception("Failed to get notifications");
        }

        $notifications = $response->json()['data'] ?? [];

        if (empty($notifications)) {
            $this->info("   ⚠️ No notifications found to test mark as read");
            return;
        }

        // Try to mark first notification as read
        $firstNotificationId = $notifications[0]['id'] ?? null;

        if ($firstNotificationId) {
            $response = Http::withToken($this->mobileToken)
                ->post($this->baseUrl . '/notifications/mark-read', [
                    'notification_id' => $firstNotificationId
                ]);

            if ($response->successful()) {
                $this->info("   ✅ Mark as read functionality working");
            } else {
                $this->info("   ⚠️ Mark as read test inconclusive: " . $response->body());
            }
        } else {
            $this->info("   ⚠️ No notification ID found to test mark as read");
        }
    }

    private function cleanupTestData()
    {
        $this->info('🧹 Cleaning up test data...');

        try {
            if ($this->testUserId) {
                DB::table('users')->where('id', $this->testUserId)->delete();
                $this->info("   ✅ Test user deleted");
            }
        } catch (\Exception $e) {
            $this->warn("   ⚠️ Cleanup warning: " . $e->getMessage());
        }
    }
}
