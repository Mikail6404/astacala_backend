<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\DisasterReport;
use App\Http\Services\CrossPlatformDataMapper;
use App\Http\Services\CrossPlatformValidator;
use Illuminate\Support\Facades\Hash;

/**
 * Cross-Platform Integration Feature Tests
 * 
 * Tests the cross-platform integration components including:
 * - Data mapping between mobile and web formats
 * - Cross-platform validation service
 * - API endpoint integration
 * - Service layer functionality
 * 
 * @group integration
 * @group cross-platform
 */
class CrossPlatformIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $dataMapper;
    protected $crossValidator;
    protected $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize services
        $this->dataMapper = new CrossPlatformDataMapper();
        $this->crossValidator = new CrossPlatformValidator();

        // Create test user
        $this->testUser = User::factory()->create([
            'email' => 'test@astacala.com',
            'role' => 'VOLUNTEER'
        ]);

        $this->actingAs($this->testUser, 'sanctum');
    }

    /**
     * Test mobile data mapping to unified format
     * 
     * @test
     * @group data-mapping
     */
    public function test_mobile_data_mapping_to_unified_format()
    {
        // Arrange - Mobile app disaster report data
        $mobileData = [
            'title' => 'Flood in Central Jakarta',
            'description' => 'Heavy rainfall caused severe flooding in residential areas. Water levels reached 1.5 meters.',
            'disaster_type' => 'flood',
            'severity_level' => 'high',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'location_name' => 'Menteng, Central Jakarta',
            'address' => 'Jl. MH Thamrin No. 1',
            'estimated_affected' => 150,
            'weather_condition' => 'Heavy Rain',
            'incident_timestamp' => '2025-07-29T08:30:00Z',
            'app_version' => '1.2.0',
            'device_info' => [
                'model' => 'Samsung Galaxy S21',
                'os' => 'Android',
                'os_version' => '12'
            ],
            'location_accuracy' => 5.2,
            'network_type' => 'wifi'
        ];

        // Act - Map mobile data to unified format
        $unifiedData = $this->dataMapper->mapMobileReportToUnified($mobileData);

        // Assert - Verify mapping correctness
        $this->assertEquals($mobileData['title'], $unifiedData['title']);
        $this->assertEquals($mobileData['description'], $unifiedData['description']);
        $this->assertEquals($mobileData['disaster_type'], $unifiedData['disaster_type']);
        $this->assertEquals($mobileData['severity_level'], $unifiedData['severity_level']);
        $this->assertEquals($mobileData['latitude'], $unifiedData['latitude']);
        $this->assertEquals($mobileData['longitude'], $unifiedData['longitude']);
        $this->assertEquals($mobileData['location_name'], $unifiedData['location_name']);
        $this->assertEquals($mobileData['estimated_affected'], $unifiedData['estimated_affected']);
        $this->assertEquals('PENDING', $unifiedData['status']);
        $this->assertEquals($this->testUser->id, $unifiedData['reported_by']);

        // Verify metadata structure
        $this->assertArrayHasKey('metadata', $unifiedData);
        $this->assertArrayHasKey('source_platform', $unifiedData['metadata']);
        $this->assertEquals('mobile', $unifiedData['metadata']['source_platform']);
    }

    /**
     * Test web data mapping to unified format
     * 
     * @test
     * @group data-mapping
     */
    public function test_web_data_mapping_to_unified_format()
    {
        // Arrange - Web dashboard disaster report data
        $webData = [
            'title' => 'Earthquake in West Java',
            'description' => 'Magnitude 6.2 earthquake struck West Java region causing structural damage to buildings.',
            'disaster_type' => 'earthquake',
            'severity_level' => 'critical',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
            'location_name' => 'Bandung, West Java',
            'address' => 'Jl. Asia Afrika No. 8',
            'estimated_affected' => 500,
            'weather_condition' => 'Clear',
            'incident_timestamp' => '2025-07-29T09:15:00Z',
            'team_name' => 'West Java Response Team',
            'personnel_count' => 25,
            'contact_phone' => '+628123456789',
            'submission_method' => 'web_dashboard',
            'admin_notes' => 'High priority response required'
        ];

        // Act - Map web data to unified format
        $unifiedData = $this->dataMapper->mapWebReportToUnified($webData);

        // Assert - Verify mapping correctness
        $this->assertEquals($webData['title'], $unifiedData['title']);
        $this->assertEquals($webData['description'], $unifiedData['description']);
        $this->assertEquals($webData['disaster_type'], $unifiedData['disaster_type']);
        $this->assertEquals($webData['severity_level'], $unifiedData['severity_level']);
        $this->assertEquals($webData['latitude'], $unifiedData['latitude']);
        $this->assertEquals($webData['longitude'], $unifiedData['longitude']);
        $this->assertEquals('PENDING', $unifiedData['status']);

        // Verify metadata structure
        $this->assertArrayHasKey('metadata', $unifiedData);
        $this->assertArrayHasKey('source_platform', $unifiedData['metadata']);
        $this->assertEquals('web', $unifiedData['metadata']['source_platform']);
    }

    /**
     * Test mobile report validation service
     * 
     * @test
     * @group validation
     */
    public function test_mobile_report_validation_success()
    {
        // Arrange - Valid mobile report data
        $validMobileData = [
            'title' => 'Valid Disaster Report Title',
            'description' => 'This is a valid disaster report description with sufficient detail.',
            'disaster_type' => 'flood',
            'severity_level' => 'medium',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'location_name' => 'Jakarta',
            'incident_timestamp' => '2025-07-29T08:30:00Z',
            'estimated_affected' => 100
        ];

        // Act - Validate mobile report data
        $validatedData = $this->crossValidator->validateMobileReport($validMobileData);

        // Assert - Should return validated data without exceptions
        $this->assertIsArray($validatedData);
        $this->assertEquals($validMobileData['title'], $validatedData['title']);
        $this->assertEquals($validMobileData['description'], $validatedData['description']);
    }

    /**
     * Test mobile report validation with invalid data
     * 
     * @test
     * @group validation
     */
    public function test_mobile_report_validation_failure()
    {
        // Arrange - Invalid mobile report data
        $invalidMobileData = [
            'title' => 'Bad', // Too short
            'description' => 'Short', // Too short
            'disaster_type' => 'invalid_type',
            'severity_level' => 'invalid_severity',
            'latitude' => 200, // Invalid coordinate
            'longitude' => 200, // Invalid coordinate
            'location_name' => 'A', // Too short
            'incident_timestamp' => '2026-12-31T23:59:59Z', // Future date - should fail
        ];

        // Act & Assert - Should throw validation exception
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->crossValidator->validateMobileReport($invalidMobileData);
    }

    /**
     * Test web report validation service
     * 
     * @test
     * @group validation
     */
    public function test_web_report_validation_success()
    {
        // Arrange - Valid web report data
        $validWebData = [
            'title' => 'Valid Web Dashboard Report',
            'description' => 'This is a comprehensive disaster report submitted through the web dashboard.',
            'disaster_type' => 'earthquake',
            'severity_level' => 'high',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
            'location_name' => 'Bandung',
            'incident_timestamp' => '2025-07-29T09:15:00Z',
            'estimated_affected' => 250,
            'team_name' => 'Emergency Response Team Alpha'
        ];

        // Act - Validate web report data
        $validatedData = $this->crossValidator->validateWebReport($validWebData);

        // Assert - Should return validated data without exceptions
        $this->assertIsArray($validatedData);
        $this->assertEquals($validWebData['title'], $validatedData['title']);
        $this->assertEquals($validWebData['team_name'], $validatedData['team_name']);
    }

    /**
     * Test unified data mapping for mobile response
     * 
     * @test
     * @group response-mapping
     */
    public function test_unified_to_mobile_response_mapping()
    {
        // Arrange - Create a disaster report in database
        $report = DisasterReport::factory()->create([
            'title' => 'Test Disaster Report',
            'description' => 'Test description for response mapping',
            'disaster_type' => 'flood',
            'severity_level' => 'medium',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'location_name' => 'Test Location',
            'status' => 'VERIFIED',
            'reported_by' => $this->testUser->id
        ]);

        // Act - Map to mobile response format
        $mobileResponse = $this->dataMapper->mapUnifiedToMobileResponse($report);

        // Assert - Verify response structure
        $this->assertArrayHasKey('id', $mobileResponse);
        $this->assertArrayHasKey('title', $mobileResponse);
        $this->assertArrayHasKey('description', $mobileResponse);
        $this->assertArrayHasKey('disaster_type', $mobileResponse);
        $this->assertArrayHasKey('severity_level', $mobileResponse);
        $this->assertArrayHasKey('status', $mobileResponse);
        $this->assertArrayHasKey('latitude', $mobileResponse);
        $this->assertArrayHasKey('longitude', $mobileResponse);
        $this->assertArrayHasKey('reporter', $mobileResponse);
        $this->assertArrayHasKey('images', $mobileResponse);
        $this->assertArrayHasKey('metadata', $mobileResponse);

        // Verify data accuracy
        $this->assertEquals($report->id, $mobileResponse['id']);
        $this->assertEquals($report->title, $mobileResponse['title']);
        $this->assertEquals($report->status, $mobileResponse['status']);
        $this->assertIsFloat($mobileResponse['latitude']);
        $this->assertIsFloat($mobileResponse['longitude']);
        $this->assertIsArray($mobileResponse['reporter']);
        $this->assertIsArray($mobileResponse['images']);
    }

    /**
     * Test disaster type mapping standardization
     * 
     * @test
     * @group data-mapping
     */
    public function test_disaster_type_mapping_standardization()
    {
        // Test various disaster type inputs and expected outputs
        $testCases = [
            'flood' => 'flood',
            'FLOOD' => 'flood',
            'banjir' => 'flood',
            'earthquake' => 'earthquake',
            'gempa' => 'earthquake',
            'fire' => 'fire',
            'kebakaran' => 'fire',
            'landslide' => 'landslide',
            'tanah_longsor' => 'landslide'
        ];

        foreach ($testCases as $input => $expected) {
            $mobileData = [
                'title' => 'Test Disaster Report',
                'description' => 'Testing disaster type mapping functionality',
                'disaster_type' => $input,
                'severity_level' => 'medium',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'location_name' => 'Test Location',
                'incident_timestamp' => '2025-07-30T08:30:00Z'
            ];

            $unifiedData = $this->dataMapper->mapMobileReportToUnified($mobileData);
            $this->assertEquals(
                $expected,
                $unifiedData['disaster_type'],
                "Failed to map disaster type '$input' to '$expected'"
            );
        }
    }

    /**
     * Test severity level mapping standardization
     * 
     * @test
     * @group data-mapping
     */
    public function test_severity_level_mapping_standardization()
    {
        // Test various severity level inputs and expected outputs
        $testCases = [
            'low' => 'low',
            'LOW' => 'low',
            'rendah' => 'low',
            '1' => 'low',
            'medium' => 'medium',
            'sedang' => 'medium',
            '2' => 'medium',
            'high' => 'high',
            'tinggi' => 'high',
            '3' => 'high',
            'critical' => 'critical',
            'kritis' => 'critical',
            '4' => 'critical'
        ];

        foreach ($testCases as $input => $expected) {
            $mobileData = [
                'title' => 'Test Severity Mapping',
                'description' => 'Testing severity level mapping functionality',
                'disaster_type' => 'flood',
                'severity_level' => $input,
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'location_name' => 'Test Location',
                'incident_timestamp' => '2025-07-30T08:30:00Z'
            ];

            $unifiedData = $this->dataMapper->mapMobileReportToUnified($mobileData);
            $this->assertEquals(
                $expected,
                $unifiedData['severity_level'],
                "Failed to map severity level '$input' to '$expected'"
            );
        }
    }

    /**
     * Test coordinate validation and sanitization
     * 
     * @test
     * @group validation
     */
    public function test_coordinate_validation_and_sanitization()
    {
        // Test valid coordinates
        $validCoordinates = [
            ['lat' => -6.2088, 'lng' => 106.8456], // Jakarta
            ['lat' => -7.2575, 'lng' => 112.7521], // Surabaya
            ['lat' => -8.6500, 'lng' => 115.2167], // Bali
            ['lat' => 3.5952, 'lng' => 98.6722],   // Medan
        ];

        foreach ($validCoordinates as $coords) {
            $mobileData = [
                'title' => 'Coordinate Test Report',
                'description' => 'Testing coordinate validation functionality',
                'disaster_type' => 'flood',
                'severity_level' => 'medium',
                'latitude' => $coords['lat'],
                'longitude' => $coords['lng'],
                'location_name' => 'Test Location',
                'incident_timestamp' => '2025-07-29T08:30:00Z'
            ];

            $validatedData = $this->crossValidator->validateMobileReport($mobileData);
            $this->assertEquals($coords['lat'], $validatedData['latitude']);
            $this->assertEquals($coords['lng'], $validatedData['longitude']);
        }
    }

    /**
     * Test comprehensive service integration
     * 
     * @test
     * @group integration
     */
    public function test_comprehensive_service_integration()
    {
        // Arrange - Complete mobile report submission
        $mobileReportData = [
            'title' => 'Comprehensive Integration Test Report',
            'description' => 'This report tests the complete integration flow from mobile submission to unified storage.',
            'disaster_type' => 'earthquake',
            'severity_level' => 'high',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'location_name' => 'Jakarta Integration Test Area',
            'address' => 'Jl. Integration Test No. 123',
            'estimated_affected' => 200,
            'weather_condition' => 'Partly Cloudy',
            'incident_timestamp' => '2025-07-29T10:00:00Z',
            'app_version' => '1.3.0',
            'device_info' => [
                'model' => 'iPhone 13',
                'os' => 'iOS',
                'os_version' => '15.6'
            ],
            'location_accuracy' => 3.1,
            'network_type' => 'cellular'
        ];

        // Act - Full integration flow
        // 1. Validate mobile data
        $validatedData = $this->crossValidator->validateMobileReport($mobileReportData);
        $this->assertIsArray($validatedData);

        // 2. Map to unified format
        $unifiedData = $this->dataMapper->mapMobileReportToUnified($validatedData);
        $this->assertIsArray($unifiedData);

        // 3. Create database record (simulated)
        $report = new DisasterReport();
        $report->fill($unifiedData);
        $report->save();

        // 4. Map back to mobile response
        $mobileResponse = $this->dataMapper->mapUnifiedToMobileResponse($report);

        // Assert - Verify complete flow
        $this->assertIsArray($mobileResponse);
        $this->assertEquals($mobileReportData['title'], $mobileResponse['title']);
        $this->assertEquals($mobileReportData['description'], $mobileResponse['description']);
        $this->assertEquals($mobileReportData['disaster_type'], $mobileResponse['disaster_type']);
        $this->assertEquals($mobileReportData['severity_level'], $mobileResponse['severity_level']);
        $this->assertEquals('PENDING', $mobileResponse['status']);

        // Verify metadata preservation
        $this->assertArrayHasKey('metadata', $mobileResponse);
        $this->assertEquals('mobile', $mobileResponse['metadata']['source_platform']);
    }
}
