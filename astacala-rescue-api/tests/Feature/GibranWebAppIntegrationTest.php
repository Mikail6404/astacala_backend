<?php

namespace Tests\Feature;

use App\Http\Services\CrossPlatformDataMapper;
use App\Http\Services\GibranWebAppAdapter;
use App\Models\DisasterReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cross-Platform Integration Test Suite
 * Validates that mobile app and Gibran's web app can work together
 * through the unified backend system
 */
class GibranWebAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $testUser;

    protected GibranWebAppAdapter $gibranAdapter;

    protected CrossPlatformDataMapper $dataMapper;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->testUser = User::factory()->create([
            'name' => 'Test Admin User',
            'email' => 'admin@test.com',
            'role' => 'admin',
        ]);

        // Initialize services
        $this->gibranAdapter = new GibranWebAppAdapter;
        $this->dataMapper = app(CrossPlatformDataMapper::class);

        // Fake storage for file upload tests
        Storage::fake('public');
    }

    /**
     * Test mobile app disaster report submission works unchanged
     *
     * @test
     *
     * @group mobile-preservation
     */
    public function test_mobile_app_disaster_report_submission_unchanged()
    {
        // Arrange - Mobile app data format (matching validation rules)
        $mobileData = [
            'title' => 'Gempa Bumi Magnitude 6.2',
            'description' => 'Gempa bumi dengan kekuatan 6.2 skala richter mengguncang area Jakarta Selatan',
            'disasterType' => 'EARTHQUAKE',
            'severityLevel' => 'HIGH',
            'latitude' => -6.2607,
            'longitude' => 106.7813,
            'locationName' => 'Jakarta Selatan',
            'estimatedAffected' => 0,
            'incidentTimestamp' => '2025-07-30T14:30:00Z',
        ];

        // Act - Submit via existing mobile endpoint
        $response = $this->actingAs($this->testUser, 'sanctum')
            ->postJson('/api/v1/reports', $mobileData);

        // Assert - Mobile submission still works
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'reportId',
                    'status',
                    'submittedAt',
                    'imageUrls',
                ],
            ]);

        // Verify report created in database
        $this->assertDatabaseHas('disaster_reports', [
            'title' => $mobileData['title'],
            'disaster_type' => 'EARTHQUAKE',
            'severity_level' => 'HIGH',
            'status' => 'PENDING',
        ]);
    }

    /**
     * Test Gibran's web app can submit disaster reports
     *
     * @test
     *
     * @group gibran-integration
     */
    public function test_gibran_web_app_pelaporan_submission()
    {
        // Arrange - Gibran's web form data format
        $gibranData = [
            'nama_team_pelapor' => 'Tim SAR Jakarta',
            'jumlah_personel' => 15,
            'no_handphone' => '081234567890',
            'informasi_singkat_bencana' => 'Banjir bandang melanda area Kemang',
            'lokasi_bencana' => 'Kemang, Jakarta Selatan',
            'titik_kordinat_lokasi_bencana' => '-6.2607,106.7813',
            'skala_bencana' => 'Tinggi',
            'jumlah_korban' => 5,
            'deskripsi_terkait_data_lainya' => 'Banjir setinggi 2 meter menggenangi area perumahan',
        ];

        // Act - Submit via Gibran compatibility endpoint
        $response = $this->actingAs($this->testUser, 'sanctum')
            ->postJson('/api/gibran/pelaporans', $gibranData);

        // Assert - Gibran submission works
        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'pelaporan_id',
            ]);

        // Verify data transformed and stored correctly
        $this->assertDatabaseHas('disaster_reports', [
            'title' => 'Banjir bandang melanda area Kemang',
            'status' => 'PENDING',
        ]);
    }

    /**
     * Test cross-platform data visibility
     * Mobile reports should appear in Gibran's web dashboard
     *
     * @test
     *
     * @group cross-platform-visibility
     */
    public function test_mobile_reports_visible_in_gibran_web_dashboard()
    {
        // Arrange - Create disaster report via mobile endpoint
        $mobileReport = DisasterReport::factory()->create([
            'title' => 'Mobile Submitted Report',
            'disaster_type' => 'flood',
            'severity_level' => 'medium',
            'reported_by' => $this->testUser->id,
            'metadata' => json_encode(['source_platform' => 'mobile']),
        ]);

        // Act - Fetch reports via Gibran's web dashboard endpoint
        $response = $this->actingAs($this->testUser, 'sanctum')
            ->getJson('/api/gibran/pelaporans');

        // Assert - Mobile report appears in web dashboard
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'disaster_type',
                        'severity_level',
                        'team_name',
                        'platform_info',
                    ],
                ],
            ]);

        // Verify specific mobile report is included
        $responseData = $response->json('data');
        $mobileReportInResponse = collect($responseData)->firstWhere('id', $mobileReport->id);

        $this->assertNotNull($mobileReportInResponse);
        $this->assertEquals('Mobile Submitted Report', $mobileReportInResponse['title']);
        $this->assertEquals('mobile', $mobileReportInResponse['platform_info']['source']);
    }

    /**
     * Test web dashboard report verification updates mobile status
     *
     * @test
     *
     * @group cross-platform-sync
     */
    public function test_web_verification_updates_mobile_status()
    {
        // Arrange - Create pending disaster report
        $report = DisasterReport::factory()->create([
            'title' => 'Pending Verification Report',
            'status' => 'PENDING',
            'reported_by' => $this->testUser->id,
        ]);

        // Act - Verify report via Gibran's web dashboard
        $verificationData = [
            'status_verifikasi' => true,
            'catatan_admin' => 'Report verified by admin',
            'status_notifikasi' => true,
        ];

        $response = $this->actingAs($this->testUser, 'sanctum')
            ->postJson("/api/gibran/pelaporans/{$report->id}/verify", $verificationData);

        // Assert - Verification successful
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Status verifikasi berhasil diperbarui',
            ]);

        // Verify status updated in database
        $report->refresh();
        $this->assertEquals('VERIFIED', $report->status);
        $this->assertEquals('Report verified by admin', $report->verification_notes);
        $this->assertEquals($this->testUser->id, $report->verified_by_admin_id);
        $this->assertNotNull($report->verified_at);

        // Verify mobile app would see updated status
        $mobileResponse = $this->actingAs($this->testUser, 'sanctum')
            ->getJson("/api/v1/reports/{$report->id}");

        $mobileResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $report->id,
                    'status' => 'VERIFIED',
                ],
            ]);
    }

    /**
     * Test Gibran's public berita-bencana endpoint
     *
     * @test
     *
     * @group public-endpoints
     */
    public function test_gibran_public_berita_bencana_endpoint()
    {
        // Arrange - Create verified reports that should appear as news
        DisasterReport::factory()->create([
            'title' => 'Verified Disaster News 1',
            'status' => 'VERIFIED',
            'severity_level' => 'high',
            'location_name' => 'Jakarta',
        ]);

        DisasterReport::factory()->create([
            'title' => 'Published Disaster News 2',
            'status' => 'ACTIVE',
            'severity_level' => 'medium',
            'location_name' => 'Bandung',
        ]);

        // Create pending report that should NOT appear
        DisasterReport::factory()->create([
            'title' => 'Pending Report',
            'status' => 'PENDING',
        ]);

        // Act - Fetch public disaster news
        $response = $this->getJson('/api/gibran/berita-bencana');

        // Assert - Only verified/published reports appear
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'pblk_judul_bencana',
                        'pblk_lokasi_bencana',
                        'pblk_skala_bencana',
                        'deskripsi_umum',
                    ],
                ],
            ]);

        $newsData = $response->json('data');
        $this->assertCount(2, $newsData); // Only verified/published reports

        // Verify content matches Gibran's format
        $newsItems = collect($newsData);
        $this->assertTrue($newsItems->contains('pblk_judul_bencana', 'Verified Disaster News 1'));
        $this->assertTrue($newsItems->contains('pblk_judul_bencana', 'Published Disaster News 2'));
        $this->assertFalse($newsItems->contains('pblk_judul_bencana', 'Pending Report'));
    }

    /**
     * Test Gibran web authentication compatibility
     *
     * @test
     *
     * @group authentication
     */
    public function test_gibran_web_authentication_compatibility()
    {
        // Arrange - Create admin user for Gibran's web app
        $webAdmin = User::factory()->create([
            'email' => 'gibran.admin@test.com',
            'password' => bcrypt('admin123'),
            'role' => 'admin',
        ]);

        // Act - Login via Gibran's web auth endpoint
        $loginData = [
            'email' => 'gibran.admin@test.com',
            'password' => 'admin123',
        ];

        $response = $this->postJson('/api/gibran/auth/login', $loginData);

        // Assert - Login successful with JWT token
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                    ],
                    'access_token',
                    'token_type',
                ],
            ]);

        $token = $response->json('data.access_token');
        $this->assertNotEmpty($token);

        // Verify token works for protected endpoints
        $protectedResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/gibran/dashboard/statistics');

        $protectedResponse->assertStatus(200);
    }

    /**
     * Test dashboard statistics for Gibran's admin panel
     *
     * @test
     *
     * @group dashboard-stats
     */
    public function test_gibran_dashboard_statistics()
    {
        // Arrange - Create test data
        DisasterReport::factory()->count(5)->create(['status' => 'PENDING']);
        DisasterReport::factory()->count(3)->create(['status' => 'VERIFIED']);
        DisasterReport::factory()->count(2)->create(['severity_level' => 'critical']);
        DisasterReport::factory()->count(4)->create(['severity_level' => 'high']);

        // Act - Fetch dashboard statistics
        $response = $this->actingAs($this->testUser, 'sanctum')
            ->getJson('/api/gibran/dashboard/statistics');

        // Assert - Statistics formatted for Gibran's dashboard
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'total_pelaporan',
                    'pelaporan_pending',
                    'pelaporan_verified',
                    'pelaporan_hari_ini',
                    'total_korban',
                    'breakdown_skala' => [
                        'ringan',
                        'sedang',
                        'tinggi',
                        'kritis',
                    ],
                    'pelaporan_terbaru',
                ],
            ]);

        $stats = $response->json('data');
        $this->assertEquals(14, $stats['total_pelaporan']); // Total reports created
        $this->assertEquals(5, $stats['pelaporan_pending']); // Pending reports
        $this->assertEquals(3, $stats['pelaporan_verified']); // Verified reports
    }

    /**
     * Test data mapping preserves both mobile and web formats
     *
     * @test
     *
     * @group data-mapping
     */
    public function test_data_mapping_preserves_both_formats()
    {
        // Arrange - Mobile data format
        $mobileData = [
            'teamName' => 'Emergency Response Team',
            'personnelCount' => 12,
            'phone' => '08123456789',
            'disasterInfo' => 'Forest Fire in Riau',
            'location' => 'Riau Province',
            'victimCount' => 3,
            'description' => 'Large forest fire spreading rapidly',
            'disasterType' => 'fire',
            'severity' => 'critical',
            'gpsLocation' => ['lat' => 0.5333, 'lng' => 101.4500],
        ];

        // Act - Transform mobile data to Gibran format
        $gibranFormat = $this->gibranAdapter->transformMobileToGibranFormat($mobileData);

        // Assert - All essential data preserved
        $this->assertEquals('Emergency Response Team', $gibranFormat['nama_team_pelapor']);
        $this->assertEquals(12, $gibranFormat['jumlah_personel']);
        $this->assertEquals('08123456789', $gibranFormat['no_handphone']);
        $this->assertEquals('Forest Fire in Riau', $gibranFormat['informasi_singkat_bencana']);
        $this->assertEquals('Riau Province', $gibranFormat['lokasi_bencana']);
        $this->assertEquals('0.5333,101.45', $gibranFormat['titik_kordinat_lokasi_bencana']);
        $this->assertEquals('Kritis', $gibranFormat['skala_bencana']);
        $this->assertEquals(3, $gibranFormat['jumlah_korban']);

        // Verify metadata preserved
        $metadata = json_decode($gibranFormat['mobile_metadata'], true);
        $this->assertEquals('mobile', $metadata['source_platform']);
        $this->assertEquals('fire', $metadata['disaster_type']);
    }

    /**
     * Test file upload handling for Gibran's web form
     *
     * @test
     *
     * @group file-upload
     */
    public function test_gibran_web_form_file_upload()
    {
        // Arrange - Create fake file (no image to avoid GD extension requirement)
        $file = UploadedFile::fake()->create('disaster.jpg', 1024, 'image/jpeg');

        $gibranData = [
            'nama_team_pelapor' => 'Tim Dokumentasi',
            'jumlah_personel' => 5,
            'no_handphone' => '081987654321',
            'informasi_singkat_bencana' => 'Landslide Documentation',
            'lokasi_bencana' => 'West Java',
            'skala_bencana' => 'Sedang',
            'jumlah_korban' => 0,
            'foto_lokasi_bencana' => $file,
        ];

        // Act - Submit with file upload
        $response = $this->actingAs($this->testUser, 'sanctum')
            ->postJson('/api/gibran/pelaporans', $gibranData);

        // Assert - Upload successful
        $response->assertStatus(201);

        // Verify file stored and linked to report
        $reportId = $response->json('pelaporan_id');
        $report = DisasterReport::find($reportId);

        $this->assertNotNull($report);
        $this->assertTrue($report->images()->exists());

        $image = $report->images()->first();
        $this->assertTrue(Storage::disk('public')->exists($image->image_path));
    }

    /**
     * Test error handling and validation
     *
     * @test
     *
     * @group error-handling
     */
    public function test_validation_errors_properly_handled()
    {
        // Arrange - Invalid Gibran data
        $invalidData = [
            'nama_team_pelapor' => '', // Required field empty
            'jumlah_personel' => -5, // Invalid number
            'no_handphone' => '', // Required field empty
            // Missing required fields
        ];

        // Act - Submit invalid data
        $response = $this->actingAs($this->testUser, 'sanctum')
            ->postJson('/api/gibran/pelaporans', $invalidData);

        // Assert - Validation errors returned
        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'message',
                'errors' => [
                    'nama_team_pelapor',
                    'jumlah_personel',
                    'no_handphone',
                    'informasi_singkat_bencana',
                    'lokasi_bencana',
                    'skala_bencana',
                ],
            ]);
    }

    /**
     * Test that existing mobile functionality is not broken
     *
     * @test
     *
     * @group mobile-preservation
     */
    public function test_mobile_app_functionality_preserved()
    {
        // Test 1: Mobile report submission
        $mobileData = [
            'title' => 'Mobile Test Report',
            'description' => 'Testing mobile submission',
            'disasterType' => 'EARTHQUAKE',
            'severityLevel' => 'MEDIUM',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'locationName' => 'Jakarta',
            'incidentTimestamp' => '2025-07-30T14:30:00Z',
        ];

        $mobileResponse = $this->actingAs($this->testUser, 'sanctum')
            ->postJson('/api/v1/reports', $mobileData);

        $mobileResponse->assertStatus(201);

        // Test 2: Mobile report listing
        $listResponse = $this->actingAs($this->testUser, 'sanctum')
            ->getJson('/api/v1/reports');

        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reports' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'disaster_type',
                            'severity_level',
                            'status',
                        ],
                    ],
                ],
            ]);

        // Test 3: Mobile statistics endpoint
        $statsResponse = $this->actingAs($this->testUser, 'sanctum')
            ->getJson('/api/v1/reports/statistics');

        $statsResponse->assertStatus(200);

        // All mobile endpoints should continue working unchanged
        $this->assertTrue(true, 'Mobile app functionality preserved');
    }
}
