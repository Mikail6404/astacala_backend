<?php

/**
 * Cross-Platform Data Synchronization Test
 * Week 5 Day 1-2 Frontend-Backend Integration Testing
 * Tests data consistency between mobile and web platforms
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\DisasterReport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CrossPlatformSyncTest
{
    private $baseUrl = 'http://localhost:8000/api/v1';
    private $mobileToken = null;
    private $webToken = null;
    private $testUserId = null;
    private $testReportId = null;

    public function runTests()
    {
        echo "🔄 Astacala Cross-Platform Data Synchronization Test\n";
        echo "====================================================\n\n";

        try {
            $this->setupTestEnvironment();
            $this->testCrossPlatformAuthentication();
            $this->testDataSynchronization();
            $this->testRealTimeUpdates();
            $this->cleanupTestData();

            echo "\n✅ Cross-platform synchronization tests completed successfully!\n";
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            $this->cleanupTestData();
        }
    }

    private function setupTestEnvironment()
    {
        echo "🛠️ Setting up test environment...\n";

        // Create test user for both platforms
        $testUser = User::create([
            'name' => 'Cross Platform Test User',
            'email' => 'crossplatform@test.com',
            'password' => Hash::make('testpassword123'),
            'is_active' => true,
            'role' => 'user'
        ]);

        $this->testUserId = $testUser->id;
        echo "   ✅ Test user created (ID: {$this->testUserId})\n";
    }

    private function testCrossPlatformAuthentication()
    {
        echo "\n🔐 Testing cross-platform authentication...\n";

        // Authenticate via mobile API
        $mobileAuth = $this->apiRequest('POST', '/auth/login', [
            'email' => 'crossplatform@test.com',
            'password' => 'testpassword123'
        ]);

        if (!isset($mobileAuth['access_token'])) {
            throw new Exception("Mobile authentication failed");
        }

        $this->mobileToken = $mobileAuth['access_token'];
        echo "   ✅ Mobile authentication successful\n";

        // Simulate web authentication (same token should work)
        $profileCheck = $this->apiRequest('GET', '/user/profile', [], $this->mobileToken);

        if (!isset($profileCheck['user'])) {
            throw new Exception("Token validation failed across platforms");
        }

        echo "   ✅ Cross-platform token validation successful\n";
    }

    private function testDataSynchronization()
    {
        echo "\n📊 Testing data synchronization...\n";

        // Create disaster report via mobile
        $mobileReport = $this->apiRequest('POST', '/reports', [
            'title' => 'Cross-Platform Test Report',
            'description' => 'Testing data sync between mobile and web',
            'location' => 'Test Location',
            'disaster_type' => 'flood',
            'severity' => 'medium',
            'status' => 'active',
            'latitude' => -6.2088,
            'longitude' => 106.8456
        ], $this->mobileToken);

        if (!isset($mobileReport['report']['id'])) {
            throw new Exception("Failed to create report via mobile");
        }

        $this->testReportId = $mobileReport['report']['id'];
        echo "   ✅ Report created via mobile (ID: {$this->testReportId})\n";

        // Retrieve same report via web API simulation
        $webReport = $this->apiRequest('GET', "/reports/{$this->testReportId}", [], $this->mobileToken);

        if (!isset($webReport['report'])) {
            throw new Exception("Failed to retrieve report via web");
        }

        // Verify data consistency
        if ($webReport['report']['title'] !== 'Cross-Platform Test Report') {
            throw new Exception("Data inconsistency detected between platforms");
        }

        echo "   ✅ Data consistency verified between platforms\n";
    }

    private function testRealTimeUpdates()
    {
        echo "\n⚡ Testing real-time update synchronization...\n";

        // Update report via mobile
        $updateData = [
            'title' => 'Updated Cross-Platform Test Report',
            'status' => 'resolved'
        ];

        $updateResponse = $this->apiRequest('PUT', "/reports/{$this->testReportId}", $updateData, $this->mobileToken);

        if (!isset($updateResponse['report'])) {
            throw new Exception("Failed to update report via mobile");
        }

        echo "   ✅ Report updated via mobile\n";

        // Verify update is immediately visible via web
        $verifyUpdate = $this->apiRequest('GET', "/reports/{$this->testReportId}", [], $this->mobileToken);

        if (
            $verifyUpdate['report']['title'] !== 'Updated Cross-Platform Test Report' ||
            $verifyUpdate['report']['status'] !== 'resolved'
        ) {
            throw new Exception("Real-time update synchronization failed");
        }

        echo "   ✅ Real-time synchronization verified\n";
    }

    private function apiRequest($method, $endpoint, $data = [], $token = null)
    {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'GET':
            default:
                // GET is default
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response) {
            throw new Exception("API request failed for $method $endpoint");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new Exception("API error ($httpCode): " . ($decoded['message'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    private function cleanupTestData()
    {
        echo "\n🧹 Cleaning up test data...\n";

        try {
            if ($this->testReportId) {
                DB::table('disaster_reports')->where('id', $this->testReportId)->delete();
                echo "   ✅ Test report deleted\n";
            }

            if ($this->testUserId) {
                DB::table('users')->where('id', $this->testUserId)->delete();
                echo "   ✅ Test user deleted\n";
            }
        } catch (Exception $e) {
            echo "   ⚠️ Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

// Run the test
$test = new CrossPlatformSyncTest();
$test->runTests();
