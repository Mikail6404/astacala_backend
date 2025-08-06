<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Log;

/**
 * Gibran Web App Compatibility Layer
 * Transforms data between mobile backend and Gibran's web application format
 * Preserves existing web app functionality while enabling cross-platform integration
 */
class GibranWebAppAdapter
{
    /**
     * Transform mobile disaster report to Gibran's pelaporan format
     *
     * @param  array  $mobileData  Data from mobile app submission
     * @return array Data formatted for Gibran's web app
     */
    public function transformMobileToGibranFormat(array $mobileData): array
    {
        return [
            // Direct field mappings
            'nama_team_pelapor' => $mobileData['teamName'] ?? $mobileData['title'] ?? '',
            'jumlah_personel' => $this->sanitizeInteger($mobileData['personnelCount'] ?? $mobileData['estimated_affected'] ?? 0),
            'no_handphone' => $mobileData['phone'] ?? $mobileData['contact_phone'] ?? '',
            'informasi_singkat_bencana' => $mobileData['disasterInfo'] ?? $mobileData['title'] ?? '',
            'lokasi_bencana' => $mobileData['location'] ?? $mobileData['location_name'] ?? '',
            'titik_kordinat_lokasi_bencana' => $this->formatCoordinatesForGibran($mobileData),
            'skala_bencana' => $this->mapSeverityToGibranScale($mobileData['severity'] ?? $mobileData['severity_level'] ?? 'medium'),
            'jumlah_korban' => $this->sanitizeInteger($mobileData['victimCount'] ?? $mobileData['casualties'] ?? 0),
            'foto_lokasi_bencana' => $this->formatImagesForGibran($mobileData['images'] ?? []),
            'bukti_surat_perintah_tugas' => null, // Web-specific field, not in mobile
            'deskripsi_terkait_data_lainya' => $mobileData['description'] ?? $mobileData['additionalNotes'] ?? '',

            // Status mappings
            'status_notifikasi' => false, // Default for new reports
            'status_verifikasi' => false, // Default for new reports

            // Metadata preservation
            'mobile_metadata' => json_encode([
                'source_platform' => 'mobile',
                'disaster_type' => $mobileData['disasterType'] ?? $mobileData['disaster_type'] ?? '',
                'original_coordinates' => [
                    'latitude' => $mobileData['latitude'] ?? $mobileData['gpsLocation']['lat'] ?? null,
                    'longitude' => $mobileData['longitude'] ?? $mobileData['gpsLocation']['lng'] ?? null,
                ],
                'submission_timestamp' => now()->toISOString(),
                'device_info' => $mobileData['device_info'] ?? null,
            ]),

            // Foreign key mapping
            'pelapor_pengguna_id' => auth()->id() ?? $mobileData['user_id'] ?? null,

            // Timestamps
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Transform Gibran's pelaporan data to mobile format
     *
     * @param  object  $gibranReport  Gibran's pelaporan model
     * @return array Data formatted for mobile app
     */
    public function transformGibranToMobileFormat(object $gibranReport): array
    {
        // Parse mobile metadata if available
        $mobileMetadata = [];
        if (! empty($gibranReport->mobile_metadata)) {
            $mobileMetadata = json_decode($gibranReport->mobile_metadata, true) ?? [];
        }

        return [
            'id' => $gibranReport->id,
            'title' => $gibranReport->informasi_singkat_bencana ?? $gibranReport->nama_team_pelapor,
            'description' => $gibranReport->deskripsi_terkait_data_lainya ?? '',
            'disaster_type' => $mobileMetadata['disaster_type'] ?? $this->mapGibranScaleToDisasterType($gibranReport->skala_bencana ?? ''),
            'severity_level' => $this->mapGibranScaleToSeverity($gibranReport->skala_bencana ?? 'medium'),
            'status' => $this->mapGibranStatusToMobile($gibranReport),

            // Location data
            'location_name' => $gibranReport->lokasi_bencana ?? '',
            'coordinates' => $this->parseGibranCoordinates($gibranReport->titik_kordinat_lokasi_bencana ?? ''),
            'latitude' => $mobileMetadata['original_coordinates']['latitude'] ?? null,
            'longitude' => $mobileMetadata['original_coordinates']['longitude'] ?? null,

            // Team and casualties
            'team_name' => $gibranReport->nama_team_pelapor ?? '',
            'personnel_count' => $gibranReport->jumlah_personel ?? 0,
            'casualties' => $gibranReport->estimated_affected ?? 0,
            'contact_phone' => $gibranReport->no_handphone ?? '',

            // Media
            'images' => $this->parseGibranImages($gibranReport->foto_lokasi_bencana ?? ''),
            'evidence_document' => $gibranReport->bukti_surat_perintah_tugas ?? null,

            // Status and verification
            'notification_status' => $gibranReport->status_notifikasi ?? false,
            'verification_status' => $gibranReport->status_verifikasi ?? false,

            // Reporter information
            'reporter' => [
                'id' => $gibranReport->pelapor_pengguna_id,
                'name' => $gibranReport->pengguna->nama ?? 'Unknown',
                'email' => $gibranReport->pengguna->email ?? '',
                'phone' => $gibranReport->pengguna->no_hp ?? '',
            ],

            // Timestamps
            'created_at' => $gibranReport->created_at?->toISOString(),
            'updated_at' => $gibranReport->updated_at?->toISOString(),

            // Platform metadata
            'platform_info' => [
                'source' => 'web_dashboard',
                'processed_by' => 'gibran_web_adapter',
                'original_format' => 'pelaporan_table',
            ],
        ];
    }

    /**
     * Transform mobile disaster report to unified backend format
     * This ensures compatibility with existing mobile backend while supporting web data
     */
    public function transformMobileToUnifiedBackend(array $mobileData): array
    {
        return [
            'title' => $mobileData['disasterInfo'] ?? $mobileData['title'] ?? $mobileData['teamName'],
            'description' => $this->buildMobileDescription($mobileData),
            'disaster_type' => $this->standardizeDisasterType($mobileData['disasterType'] ?? ''),
            'severity_level' => $this->standardizeSeverityLevel($mobileData['severity'] ?? 'medium'),
            'latitude' => $this->sanitizeCoordinate($mobileData['gpsLocation']['lat'] ?? $mobileData['latitude'] ?? 0),
            'longitude' => $this->sanitizeCoordinate($mobileData['gpsLocation']['lng'] ?? $mobileData['longitude'] ?? 0),
            'location_name' => $mobileData['location'] ?? '',
            'estimated_affected' => $this->sanitizeInteger($mobileData['victimCount'] ?? 0),
            'status' => 'PENDING',
            'reported_by' => auth()->id(),

            // Additional fields for web compatibility
            'team_name' => $mobileData['teamName'] ?? '',
            'personnel_count' => $this->sanitizeInteger($mobileData['personnelCount'] ?? 0),
            'contact_phone' => $mobileData['phone'] ?? '',

            // Metadata for cross-platform tracking
            'metadata' => json_encode([
                'source_platform' => 'mobile',
                'original_data' => $mobileData,
                'gibran_compatible' => true,
                'transformation_timestamp' => now()->toISOString(),
            ]),

            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Format coordinates for Gibran's web application
     * Gibran's app expects coordinates as a string
     */
    private function formatCoordinatesForGibran(array $data): string
    {
        $lat = $data['gpsLocation']['lat'] ?? $data['latitude'] ?? null;
        $lng = $data['gpsLocation']['lng'] ?? $data['longitude'] ?? null;

        if ($lat && $lng) {
            return "{$lat},{$lng}";
        }

        return $data['coordinates'] ?? '';
    }

    /**
     * Parse Gibran's coordinate string format
     */
    private function parseGibranCoordinates(string $coordinates): array
    {
        if (strpos($coordinates, ',') !== false) {
            [$lat, $lng] = explode(',', $coordinates, 2);

            return [
                'latitude' => (float) trim($lat),
                'longitude' => (float) trim($lng),
            ];
        }

        return ['latitude' => null, 'longitude' => null];
    }

    /**
     * Map mobile severity to Gibran's scale format
     */
    private function mapSeverityToGibranScale(string $severity): string
    {
        $mapping = [
            'low' => 'Ringan',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'critical' => 'Kritis',
            // Handle numeric scales
            '1' => 'Ringan',
            '2' => 'Sedang',
            '3' => 'Tinggi',
            '4' => 'Kritis',
        ];

        return $mapping[strtolower($severity)] ?? 'Sedang';
    }

    /**
     * Map Gibran's scale back to mobile severity levels
     */
    private function mapGibranScaleToSeverity(string $scale): string
    {
        $mapping = [
            'ringan' => 'low',
            'sedang' => 'medium',
            'tinggi' => 'high',
            'kritis' => 'critical',
        ];

        return $mapping[strtolower($scale)] ?? 'medium';
    }

    /**
     * Map Gibran's scale to disaster type (best guess)
     */
    private function mapGibranScaleToDisasterType(string $scale): string
    {
        // Since Gibran's scale is severity, not type, we need to make educated guesses
        // or rely on metadata
        return 'other'; // Default when type cannot be determined
    }

    /**
     * Format images for Gibran's single image field
     */
    private function formatImagesForGibran(array $images): ?string
    {
        if (empty($images)) {
            return null;
        }

        // Take the first image as primary for Gibran's format
        if (is_array($images[0])) {
            return $images[0]['path'] ?? $images[0]['url'] ?? null;
        }

        return $images[0] ?? null;
    }

    /**
     * Parse Gibran's single image back to array format
     */
    private function parseGibranImages(?string $imagePath): array
    {
        if (empty($imagePath)) {
            return [];
        }

        return [
            [
                'id' => null,
                'url' => $imagePath,
                'is_primary' => true,
            ],
        ];
    }

    /**
     * Map Gibran's verification status to mobile status
     */
    private function mapGibranStatusToMobile(object $gibranReport): string
    {
        if ($gibranReport->status_verifikasi) {
            return 'VERIFIED';
        }

        if ($gibranReport->status_notifikasi) {
            return 'PENDING';
        }

        return 'PENDING';
    }

    /**
     * Build comprehensive description from mobile data
     */
    private function buildMobileDescription(array $data): string
    {
        $parts = [];

        if (! empty($data['teamName'])) {
            $parts[] = "Tim Pelapor: {$data['teamName']}";
        }

        if (! empty($data['personnelCount'])) {
            $parts[] = "Jumlah Personel: {$data['personnelCount']}";
        }

        if (! empty($data['phone'])) {
            $parts[] = "Kontak: {$data['phone']}";
        }

        if (! empty($data['description'])) {
            $parts[] = "Deskripsi: {$data['description']}";
        }

        if (! empty($data['additionalNotes'])) {
            $parts[] = "Catatan Tambahan: {$data['additionalNotes']}";
        }

        return implode("\n", $parts);
    }

    /**
     * Standardize disaster type for consistency
     */
    private function standardizeDisasterType(string $type): string
    {
        $mapping = [
            'gempa bumi' => 'earthquake',
            'gempa' => 'earthquake',
            'banjir' => 'flood',
            'kebakaran' => 'fire',
            'tanah longsor' => 'landslide',
            'angin topan' => 'hurricane',
        ];

        $normalized = strtolower(trim($type));

        return $mapping[$normalized] ?? $normalized;
    }

    /**
     * Standardize severity level for consistency
     */
    private function standardizeSeverityLevel(string $severity): string
    {
        $mapping = [
            'rendah' => 'low',
            'sedang' => 'medium',
            'tinggi' => 'high',
            'kritis' => 'critical',
        ];

        $normalized = strtolower(trim($severity));

        return $mapping[$normalized] ?? $normalized;
    }

    /**
     * Sanitize integer values
     */
    private function sanitizeInteger($value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Sanitize coordinate values
     */
    private function sanitizeCoordinate($value): float
    {
        $coord = is_numeric($value) ? (float) $value : 0.0;

        // Basic validation for reasonable coordinate ranges
        if (abs($coord) > 180) {
            return 0.0;
        }

        return $coord;
    }

    /**
     * Log transformation for debugging
     */
    private function logTransformation(string $direction, array $input, array $output): void
    {
        Log::info("Gibran Web App Adapter - {$direction}", [
            'input_keys' => array_keys($input),
            'output_keys' => array_keys($output),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get transformation statistics
     */
    public function getTransformationStats(): array
    {
        return [
            'adapter_version' => '1.0.0',
            'supported_directions' => [
                'mobile_to_gibran',
                'gibran_to_mobile',
                'mobile_to_unified',
            ],
            'field_mappings' => [
                'direct_mappings' => 8,
                'transformed_mappings' => 4,
                'metadata_fields' => 3,
            ],
            'compatibility' => [
                'mobile_app' => '100%',
                'gibran_web_app' => '100%',
                'unified_backend' => '100%',
            ],
        ];
    }

    /**
     * Transform Gibran validated data to unified backend format
     *
     * @param  array  $gibranData  Validated data from Gibran's web form
     * @return array Data formatted for mobile backend database
     */
    public function transformGibranToUnifiedBackend(array $gibranData): array
    {
        // Parse coordinates from Gibran format
        $coordinates = $this->parseGibranCoordinates($gibranData['titik_kordinat_lokasi_bencana'] ?? '');

        return [
            'title' => $gibranData['informasi_singkat_bencana'] ?? 'Laporan Bencana',
            'description' => $this->buildGibranDescription($gibranData),
            'disaster_type' => $this->standardizeDisasterType($gibranData['jenis_bencana'] ?? 'OTHER'),
            'severity_level' => $this->standardizeSeverityLevel($gibranData['skala_bencana'] ?? 'medium'),
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'location_name' => $gibranData['lokasi_bencana'] ?? '',
            'address' => $gibranData['lokasi_bencana'] ?? '',
            'estimated_affected' => $this->sanitizeInteger($gibranData['jumlah_korban'] ?? 0),
            'status' => 'PENDING',
            'incident_timestamp' => now(),
            'reported_by' => auth()->id(),

            // Additional fields for web compatibility
            'team_name' => $gibranData['nama_team_pelapor'] ?? '',
            'metadata' => [
                'source_platform' => 'web',
                'submission_method' => 'gibran_web_form',
                'personnel_count' => $this->sanitizeInteger($gibranData['jumlah_personel'] ?? 0),
                'contact_phone' => $gibranData['no_handphone'] ?? '',
                'additional_notes' => $gibranData['deskripsi_terkait_data_lainya'] ?? '',
            ],
        ];
    }

    /**
     * Build description from Gibran data
     */
    private function buildGibranDescription(array $gibranData): string
    {
        $description = $gibranData['informasi_singkat_bencana'] ?? '';

        if (! empty($gibranData['deskripsi_terkait_data_lainya'])) {
            $description .= "\n\nInformasi Tambahan: ".$gibranData['deskripsi_terkait_data_lainya'];
        }

        return $description;
    }
}
