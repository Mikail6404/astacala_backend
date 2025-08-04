<?php

/**
 * Phase 3 Cross-Platform Integration Validation Test
 * 
 * Tests if disaster reports submitted via mobile API actually appear 
 * in web dashboard and if the full cross-platform flow works.
 */

// No composer autoload needed for this test

class Phase3IntegrationTest
{
    private $baseUrl = 'http://127.0.0.1:8000/api';
    private $authToken = null;

    public function __construct()
    {
        echo "🧪 Phase 3 Cross-Platform Integration Test\n";
        echo "==========================================\n\n";
    }

    /**
     * Test 1: Authenticate user
     */
    public function testAuthentication()
    {
        echo "📱 Test 1: Authentication via API\n";

        $response = $this->makeRequest('POST', '/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        if ($response && isset($response['success']) && $response['success']) {
            $this->authToken = $response['data']['token'];
            echo "✅ Authentication successful\n";
            echo "🔑 Token: " . substr($this->authToken, 0, 20) . "...\n\n";
            return true;
        } else {
            echo "❌ Authentication failed\n";
            echo "📄 Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
            return false;
        }
    }

    /**
     * Test 2: Submit disaster report via mobile API
     */
    public function testDisasterReportSubmission()
    {
        echo "📱 Test 2: Submit disaster report via Mobile API\n";

        if (!$this->authToken) {
            echo "❌ No auth token available\n\n";
            return false;
        }

        $testReport = [
            'type' => 'FLOOD',
            'description' => 'Phase 3 integration test flood report',
            'location' => 'Test Location for Phase 3',
            'coordinates' => '-6.2088,106.8456', // Jakarta coordinates
            'severity' => 'MEDIUM',
            'casualties' => 0,
            'additional_info' => 'Automated test for cross-platform integration validation'
        ];

        $response = $this->makeRequest('POST', '/v1/reports', $testReport);

        if ($response && isset($response['success']) && $response['success']) {
            echo "✅ Disaster report submitted successfully\n";
            echo "📊 Report ID: " . $response['data']['id'] . "\n";
            echo "📍 Location: " . $response['data']['location'] . "\n\n";
            return $response['data']['id'];
        } else {
            echo "❌ Disaster report submission failed\n";
            echo "📄 Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
            return false;
        }
    }

    /**
     * Test 3: Verify report appears in API listings
     */
    public function testReportRetrieval($reportId)
    {
        echo "📱 Test 3: Verify report appears in API listings\n";

        if (!$this->authToken || !$reportId) {
            echo "❌ Missing auth token or report ID\n\n";
            return false;
        }

        $response = $this->makeRequest('GET', '/v1/reports');

        if ($response && isset($response['success']) && $response['success']) {
            $reports = $response['data'];
            $foundReport = false;

            foreach ($reports as $report) {
                if ($report['id'] == $reportId) {
                    $foundReport = true;
                    echo "✅ Report found in API listings\n";
                    echo "📊 Report ID: " . $report['id'] . "\n";
                    echo "📍 Location: " . $report['location'] . "\n";
                    echo "⏰ Created: " . $report['created_at'] . "\n\n";
                    break;
                }
            }

            if (!$foundReport) {
                echo "❌ Report not found in listings\n\n";
                return false;
            }

            return true;
        } else {
            echo "❌ Failed to retrieve reports\n";
            echo "📄 Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
            return false;
        }
    }

    /**
     * Test 4: Check database consistency
     */
    public function testDatabaseConsistency()
    {
        echo "🗄️ Test 4: Database consistency check\n";

        try {
            // Use artisan command to check database
            $output = shell_exec('cd "' . __DIR__ . '/../" && php artisan tinker --execute="echo \'Total reports: \' . App\\Models\\DisasterReport::count();"');

            if (strpos($output, 'Total reports:') !== false) {
                echo "✅ Database accessible\n";
                echo "📊 " . trim($output) . "\n\n";
                return true;
            } else {
                echo "❌ Database check failed\n";
                echo "📄 Output: " . $output . "\n\n";
                return false;
            }
        } catch (Exception $e) {
            echo "❌ Database check error: " . $e->getMessage() . "\n\n";
            return false;
        }
    }

    /**
     * Test 5: Web dashboard integration test
     */
    public function testWebDashboardIntegration()
    {
        echo "🌐 Test 5: Web dashboard integration check\n";

        // Check if web app can access the same data
        $webAppPath = __DIR__ . '/../../astacala_resque-main/astacala_rescue_web';

        if (file_exists($webAppPath . '/app/Http/Controllers/PelaporanController.php')) {
            echo "✅ Web dashboard controller exists\n";
            echo "📁 Path: " . $webAppPath . "\n";

            // Check if web app uses the same database
            $webEnvPath = $webAppPath . '/.env';
            if (file_exists($webEnvPath)) {
                $webEnv = file_get_contents($webEnvPath);
                if (strpos($webEnv, 'astacala_rescue') !== false) {
                    echo "✅ Web app uses unified database\n\n";
                    return true;
                } else {
                    echo "⚠️ Web app may use different database\n\n";
                    return false;
                }
            } else {
                echo "⚠️ Web app .env file not found\n\n";
                return false;
            }
        } else {
            echo "❌ Web dashboard controller not found\n\n";
            return false;
        }
    }

    /**
     * Helper method to make HTTP requests
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->authToken) {
            $headers[] = 'Authorization: Bearer ' . $this->authToken;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['error' => 'HTTP request failed'];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $decoded;
        } else {
            return ['error' => 'HTTP ' . $httpCode, 'response' => $decoded];
        }
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        $results = [];

        $results['auth'] = $this->testAuthentication();

        if ($results['auth']) {
            $reportId = $this->testDisasterReportSubmission();
            $results['submission'] = $reportId !== false;

            if ($reportId) {
                $results['retrieval'] = $this->testReportRetrieval($reportId);
            } else {
                $results['retrieval'] = false;
            }
        } else {
            $results['submission'] = false;
            $results['retrieval'] = false;
        }

        $results['database'] = $this->testDatabaseConsistency();
        $results['web_integration'] = $this->testWebDashboardIntegration();

        // Summary
        echo "🏁 Phase 3 Integration Test Results\n";
        echo "==================================\n";
        echo "📱 Authentication: " . ($results['auth'] ? '✅ PASS' : '❌ FAIL') . "\n";
        echo "📊 Report Submission: " . ($results['submission'] ? '✅ PASS' : '❌ FAIL') . "\n";
        echo "🔍 Report Retrieval: " . ($results['retrieval'] ? '✅ PASS' : '❌ FAIL') . "\n";
        echo "🗄️ Database Consistency: " . ($results['database'] ? '✅ PASS' : '❌ FAIL') . "\n";
        echo "🌐 Web Integration: " . ($results['web_integration'] ? '✅ PASS' : '❌ FAIL') . "\n\n";

        $passCount = array_sum($results);
        $totalTests = count($results);

        echo "📈 Overall Result: $passCount/$totalTests tests passed\n";

        if ($passCount == $totalTests) {
            echo "🎉 Phase 3 Core Functionality Integration: READY TO COMPLETE\n";
            return true;
        } else {
            echo "⚠️ Phase 3 Integration Issues Found - Requires fixes\n";
            return false;
        }
    }
}

// Run the test
$test = new Phase3IntegrationTest();
$test->runAllTests();
