<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\DisasterReport;

class Week7BasicIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_week7_mobile_to_web_workflow()
    {
        echo "\n🎯 WEEK 7 BASIC INTEGRATION TEST\n";

        // Create test users
        $volunteer = User::factory()->create(['role' => 'VOLUNTEER']);
        $admin = User::factory()->create(['role' => 'ADMIN']);

        echo "STEP 1: Creating disaster report via mobile API...\n";

        // Mobile volunteer creates report
        $response = $this->actingAs($volunteer)
            ->postJson('/api/v1/reports', [
                'title' => 'Week 7 Test Flood Report',
                'description' => 'Major flooding reported in test area',
                'disasterType' => 'FLOOD',
                'severityLevel' => 'MEDIUM',
                'latitude' => 1.2966,
                'longitude' => 124.8419,
                'locationName' => 'Test Location',
                'estimatedAffected' => 50,
                'incidentTimestamp' => now()->toISOString(),
                'platform' => 'mobile'
            ]);

        if ($response->getStatusCode() !== 201) {
            echo "❌ Failed to create report. Status: " . $response->getStatusCode() . "\n";
            echo "Response: " . $response->getContent() . "\n";
            $this->fail("Report creation failed");
        }

        echo "✅ Report created successfully. Status: " . $response->getStatusCode() . "\n";
        echo "Response data: " . $response->getContent() . "\n";

        $responseData = $response->json();
        if (!isset($responseData['data']['reportId'])) {
            echo "❌ Response structure unexpected. Looking for ['data']['reportId']\n";
            echo "Available keys in response: " . implode(', ', array_keys($responseData)) . "\n";
            if (isset($responseData['data'])) {
                echo "Available keys in data: " . implode(', ', array_keys($responseData['data'])) . "\n";
            }
            $this->fail("Report ID not found in response");
        }

        $reportData = $responseData['data'];
        $reportId = $reportData['reportId'];

        echo "✅ Report created successfully (ID: {$reportId})\n";

        echo "STEP 2: Admin retrieves and verifies report...\n";

        // Admin retrieves report
        $getResponse = $this->actingAs($admin)
            ->getJson("/api/v1/reports/{$reportId}");

        $getResponse->assertStatus(200);
        echo "✅ Admin can access mobile-created report\n";

        // Admin verifies report
        $updateResponse = $this->actingAs($admin)
            ->putJson("/api/v1/reports/{$reportId}", [
                'status' => 'ACTIVE',
                'verification_status' => 'verified',
                'verification_notes' => 'Report verified by admin',
                'platform' => 'web'
            ]);

        $updateResponse->assertStatus(200);
        echo "✅ Admin verification completed\n";

        echo "STEP 3: Validating cross-platform sync...\n";

        // Volunteer sees admin updates
        $finalCheck = $this->actingAs($volunteer)
            ->getJson("/api/v1/reports/{$reportId}");

        $finalCheck->assertStatus(200);
        $finalData = $finalCheck->json()['data'];

        $this->assertEquals('ACTIVE', $finalData['status']);
        echo "✅ Cross-platform synchronization working\n";

        echo "\n🎯 WEEK 7 BASIC INTEGRATION: COMPLETE ✅\n";

        return true;
    }
}
