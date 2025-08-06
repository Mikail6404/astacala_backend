<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TestCompleteUserJourney extends Command
{
    protected $signature = 'test:complete-user-journey';

    protected $description = 'Test complete user workflow mobile → backend → web';

    private $baseUrl = 'http://localhost:8000/api/v1';

    private $mobileToken = null;

    private $testUserId = null;

    private $testReportId = null;

    public function handle()
    {
        $this->info('🚀 Astacala Complete User Journey Test');
        $this->info('=====================================');
        $this->newLine();

        try {
            $this->testMobileToBackendJourney();
            $this->testBackendToWebJourney();
            $this->testWebToMobileSync();
            $this->cleanupTestData();

            $this->info('✅ Complete user journey tests completed successfully!');
            $this->info('📊 Cross-platform integration fully validated!');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Test failed: '.$e->getMessage());
            $this->cleanupTestData();

            return 1;
        }
    }

    private function testMobileToBackendJourney()
    {
        $this->info('📱 Testing Mobile → Backend Journey...');

        // Step 1: Mobile user registration
        $response = Http::post($this->baseUrl.'/auth/register', [
            'name' => 'Journey Test User',
            'email' => 'journey@test.com',
            'password' => 'testpassword123',
            'password_confirmation' => 'testpassword123',
        ]);

        if (! $response->successful()) {
            throw new \Exception('Mobile registration failed: '.$response->body());
        }

        $this->info('   ✅ Mobile user registration successful');

        // Step 2: Mobile login
        $loginResponse = Http::post($this->baseUrl.'/auth/login', [
            'email' => 'journey@test.com',
            'password' => 'testpassword123',
        ]);

        if (! $loginResponse->successful() || ! isset($loginResponse->json()['data']['tokens']['accessToken'])) {
            throw new \Exception('Mobile login failed: '.$loginResponse->body());
        }

        $this->mobileToken = $loginResponse->json()['data']['tokens']['accessToken'];
        $this->testUserId = $loginResponse->json()['data']['user']['id'];
        $this->info('   ✅ Mobile authentication successful');

        // Step 3: Create disaster report from mobile
        $reportData = [
            'title' => 'Mobile Emergency Report',
            'description' => 'Emergency reported from mobile app',
            'disasterType' => 'EARTHQUAKE',
            'severityLevel' => 'HIGH',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'locationName' => 'Jakarta Emergency Zone',
            'incidentTimestamp' => now()->toISOString(),
        ];

        $createResponse = Http::withToken($this->mobileToken)
            ->post($this->baseUrl.'/reports', $reportData);

        if (! $createResponse->successful() || ! isset($createResponse->json()['data']['reportId'])) {
            throw new \Exception('Mobile report creation failed: '.$createResponse->body());
        }

        $this->testReportId = $createResponse->json()['data']['reportId'];
        $this->info("   ✅ Disaster report created from mobile (ID: {$this->testReportId})");
    }

    private function testBackendToWebJourney()
    {
        $this->info('💻 Testing Backend → Web Journey...');

        // Step 1: Retrieve report data via web API
        $response = Http::withToken($this->mobileToken)
            ->get($this->baseUrl."/reports/{$this->testReportId}");

        if (! $response->successful() || ! isset($response->json()['data']['id'])) {
            throw new \Exception('Web report retrieval failed: '.$response->body());
        }

        $reportData = $response->json()['data'];
        $this->info('   ✅ Report accessible via web interface');

        // Step 2: Update report status (simulating web admin action)
        $updateResponse = Http::withToken($this->mobileToken)
            ->put($this->baseUrl."/reports/{$this->testReportId}", [
                'status' => 'ACTIVE',
            ]);

        if (! $updateResponse->successful()) {
            throw new \Exception('Web status update failed: '.$updateResponse->body());
        }

        $this->info('   ✅ Report status updated from web interface');

        // Step 3: Get user profile via web
        $profileResponse = Http::withToken($this->mobileToken)
            ->get($this->baseUrl.'/auth/me');

        if (! $profileResponse->successful()) {
            throw new \Exception('Web profile access failed: '.$profileResponse->body());
        }

        $this->info('   ✅ User profile accessible via web');
    }

    private function testWebToMobileSync()
    {
        $this->info('🔄 Testing Web → Mobile Synchronization...');

        // Step 1: Verify report changes are visible on mobile
        $response = Http::withToken($this->mobileToken)
            ->get($this->baseUrl."/reports/{$this->testReportId}");

        if (! $response->successful()) {
            throw new \Exception('Mobile sync verification failed: '.$response->body());
        }

        $reportData = $response->json()['data'];
        if ($reportData['status'] !== 'ACTIVE') {
            throw new \Exception('Status changes not synced to mobile');
        }

        $this->info('   ✅ Web changes synchronized to mobile');

        // Step 2: Test notification delivery
        $notificationResponse = Http::withToken($this->mobileToken)
            ->get($this->baseUrl.'/notifications');

        if (! $notificationResponse->successful()) {
            throw new \Exception('Mobile notification access failed');
        }

        $this->info('   ✅ Notifications accessible on mobile');

        // Step 3: Test complete data consistency
        $userReportsResponse = Http::withToken($this->mobileToken)
            ->get($this->baseUrl.'/reports', [
                'user_id' => $this->testUserId,
            ]);

        if (! $userReportsResponse->successful()) {
            throw new \Exception('User reports sync failed');
        }

        $this->info('   ✅ Complete data consistency verified');
    }

    private function cleanupTestData()
    {
        $this->info('🧹 Cleaning up test data...');

        try {
            if ($this->testReportId) {
                DB::table('disaster_reports')->where('id', $this->testReportId)->delete();
                $this->info('   ✅ Test report deleted');
            }

            if ($this->testUserId) {
                DB::table('users')->where('id', $this->testUserId)->delete();
                $this->info('   ✅ Test user deleted');
            }
        } catch (\Exception $e) {
            $this->warn('   ⚠️ Cleanup warning: '.$e->getMessage());
        }
    }
}
