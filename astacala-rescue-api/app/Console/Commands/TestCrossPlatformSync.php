<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class TestCrossPlatformSync extends Command
{
    protected $signature = 'test:cross-platform-sync';

    protected $description = 'Test cross-platform data synchronization between mobile and web';

    private $baseUrl = 'http://localhost:8000/api/v1';

    private $mobileToken = null;

    private $testUserId = null;

    private $testReportId = null;

    public function handle()
    {
        $this->info('🔄 Astacala Cross-Platform Data Synchronization Test');
        $this->info('====================================================');
        $this->newLine();

        try {
            $this->setupTestEnvironment();
            $this->testCrossPlatformAuthentication();
            $this->testDataSynchronization();
            $this->testRealTimeUpdates();
            $this->cleanupTestData();

            $this->info('✅ Cross-platform synchronization tests completed successfully!');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Test failed: '.$e->getMessage());
            $this->cleanupTestData();

            return 1;
        }
    }

    private function setupTestEnvironment()
    {
        $this->info('🛠️ Setting up test environment...');

        // Create test user for both platforms
        $testUser = User::create([
            'name' => 'Cross Platform Test User',
            'email' => 'crossplatform@test.com',
            'password' => Hash::make('testpassword123'),
            'is_active' => true,
            'role' => 'VOLUNTEER',
        ]);

        $this->testUserId = $testUser->id;
        $this->info("   ✅ Test user created (ID: {$this->testUserId})");
    }

    private function testCrossPlatformAuthentication()
    {
        $this->info('🔐 Testing cross-platform authentication...');

        // Authenticate via mobile API
        $response = Http::post($this->baseUrl.'/auth/login', [
            'email' => 'crossplatform@test.com',
            'password' => 'testpassword123',
        ]);

        if (! $response->successful() || ! isset($response->json()['data']['tokens']['accessToken'])) {
            throw new \Exception('Mobile authentication failed: '.$response->body());
        }

        $this->mobileToken = $response->json()['data']['tokens']['accessToken'];
        $this->info('   ✅ Mobile authentication successful');

        // Validate token across platforms
        $profileResponse = Http::withToken($this->mobileToken)
            ->get($this->baseUrl.'/auth/me');

        if (! $profileResponse->successful()) {
            throw new \Exception('Token validation failed across platforms: '.$profileResponse->body());
        }

        $profileData = $profileResponse->json();
        if (! isset($profileData['data']['id'])) {
            throw new \Exception('Token validation failed - invalid response structure: '.$profileResponse->body());
        }

        $this->info('   ✅ Cross-platform token validation successful');
    }

    private function testDataSynchronization()
    {
        $this->info('📊 Testing data synchronization...');

        // Create disaster report via mobile
        $reportData = [
            'title' => 'Cross-Platform Test Report',
            'description' => 'Testing data sync between mobile and web',
            'disasterType' => 'FLOOD',
            'severityLevel' => 'MEDIUM',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'locationName' => 'Test Location',
            'incidentTimestamp' => now()->toISOString(),
        ];

        $createResponse = Http::withToken($this->mobileToken)
            ->post($this->baseUrl.'/reports', $reportData);

        if (! $createResponse->successful() || ! isset($createResponse->json()['data']['reportId'])) {
            throw new \Exception('Failed to create report via mobile: '.$createResponse->body());
        }

        $this->testReportId = $createResponse->json()['data']['reportId'];
        $this->info("   ✅ Report created via mobile (ID: {$this->testReportId})");

        // Retrieve same report via web API simulation
        $retrieveResponse = Http::withToken($this->mobileToken)
            ->get($this->baseUrl."/reports/{$this->testReportId}");

        if (! $retrieveResponse->successful() || ! isset($retrieveResponse->json()['data']['id'])) {
            throw new \Exception('Failed to retrieve report via web: '.$retrieveResponse->body());
        }

        // Verify data consistency
        $reportData = $retrieveResponse->json()['data'];
        if ($reportData['title'] !== 'Cross-Platform Test Report') {
            throw new \Exception('Data inconsistency detected between platforms');
        }

        $this->info('   ✅ Data consistency verified between platforms');
    }

    private function testRealTimeUpdates()
    {
        $this->info('⚡ Testing real-time update synchronization...');

        // Update report via mobile
        $updateData = [
            'status' => 'RESOLVED',
        ];

        $updateResponse = Http::withToken($this->mobileToken)
            ->put($this->baseUrl."/reports/{$this->testReportId}", $updateData);

        if (! $updateResponse->successful() || ! isset($updateResponse->json()['data'])) {
            throw new \Exception('Failed to update report via mobile: '.$updateResponse->body());
        }

        $this->info('   ✅ Report updated via mobile');

        // Verify update is immediately visible via web
        $verifyResponse = Http::withToken($this->mobileToken)
            ->get($this->baseUrl."/reports/{$this->testReportId}");

        if (! $verifyResponse->successful()) {
            throw new \Exception('Failed to verify update: '.$verifyResponse->body());
        }

        $reportData = $verifyResponse->json()['data'];
        if ($reportData['status'] !== 'RESOLVED') {
            throw new \Exception('Real-time update synchronization failed - status not updated');
        }

        $this->info('   ✅ Real-time synchronization verified');
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
