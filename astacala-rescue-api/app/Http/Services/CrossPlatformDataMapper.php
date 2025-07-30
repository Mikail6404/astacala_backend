<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Cross-Platform Data Mapping Service
 * Handles data transformation between mobile app and web dashboard formats
 */
class CrossPlatformDataMapper
{
    /**
     * Map mobile disaster report submission to unified format
     */
    public function mapMobileReportToUnified(array $mobileData): array
    {
        return [
            'title' => $mobileData['title'] ?? '',
            'description' => $mobileData['description'] ?? '',
            'disaster_type' => $this->mapDisasterType($mobileData['disaster_type'] ?? ''),
            'severity_level' => $this->mapSeverityLevel($mobileData['severity_level'] ?? ''),
            'latitude' => $this->validateCoordinate($mobileData['latitude'] ?? 0, 'latitude'),
            'longitude' => $this->validateCoordinate($mobileData['longitude'] ?? 0, 'longitude'),
            'location_name' => $mobileData['location_name'] ?? '',
            'address' => $mobileData['address'] ?? null,
            'estimated_affected' => $this->sanitizeInteger($mobileData['estimated_affected'] ?? 0),
            'weather_condition' => $mobileData['weather_condition'] ?? null,
            'incident_timestamp' => $this->validateTimestamp($mobileData['incident_timestamp'] ?? now()),
            'metadata' => $this->buildMobileMetadata($mobileData),
            'status' => 'PENDING',
            'reported_by' => auth()->id()
        ];
    }

    /**
     * Map web dashboard report submission to unified format
     */
    public function mapWebReportToUnified(array $webData): array
    {
        return [
            'title' => $webData['title'] ?? '',
            'description' => $webData['description'] ?? '',
            'disaster_type' => $this->mapDisasterType($webData['disaster_type'] ?? ''),
            'severity_level' => $this->mapSeverityLevel($webData['severity_level'] ?? ''),
            'latitude' => $this->validateCoordinate($webData['latitude'] ?? 0, 'latitude'),
            'longitude' => $this->validateCoordinate($webData['longitude'] ?? 0, 'longitude'),
            'location_name' => $webData['location_name'] ?? '',
            'address' => $webData['address'] ?? null,
            'estimated_affected' => $this->sanitizeInteger($webData['estimated_affected'] ?? 0),
            'weather_condition' => $webData['weather_condition'] ?? null,
            'incident_timestamp' => $this->validateTimestamp($webData['incident_timestamp'] ?? now()),
            'metadata' => $this->buildWebMetadata($webData),
            'status' => 'PENDING',
            'reported_by' => auth()->id()
        ];
    }

    /**
     * Map unified report data for mobile app response
     */
    public function mapUnifiedToMobileResponse(object $report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->title,
            'description' => $report->description,
            'disaster_type' => $report->disaster_type,
            'severity_level' => $report->severity_level,
            'status' => $report->status,
            'latitude' => (float) $report->latitude,
            'longitude' => (float) $report->longitude,
            'location_name' => $report->location_name,
            'address' => $report->address,
            'estimated_affected' => $report->estimated_affected,
            'weather_condition' => $report->weather_condition,
            'incident_timestamp' => $report->incident_timestamp->toISOString(),
            'created_at' => $report->created_at->toISOString(),
            'updated_at' => $report->updated_at->toISOString(),
            'reporter' => [
                'id' => $report->reporter->id ?? null,
                'name' => $report->reporter->name ?? 'Unknown',
                'email' => $report->reporter->email ?? null,
            ],
            'images' => $report->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->image_path,
                    'is_primary' => $image->is_primary,
                ];
            })->toArray(),
            'metadata' => $this->extractMobileMetadata($report->metadata ?? [])
        ];
    }

    /**
     * Map unified report data for web dashboard response
     */
    public function mapUnifiedToWebResponse(object $report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->title,
            'description' => $report->description,
            'disaster_type' => [
                'code' => $report->disaster_type,
                'label' => $this->getDisasterTypeLabel($report->disaster_type)
            ],
            'severity_level' => [
                'code' => $report->severity_level,
                'label' => $this->getSeverityLabel($report->severity_level),
                'color' => $this->getSeverityColor($report->severity_level)
            ],
            'status' => [
                'code' => $report->status,
                'label' => $this->getStatusLabel($report->status),
                'color' => $this->getStatusColor($report->status)
            ],
            'location' => [
                'latitude' => (float) $report->latitude,
                'longitude' => (float) $report->longitude,
                'name' => $report->location_name,
                'address' => $report->address,
                'coordinates' => [$report->longitude, $report->latitude] // GeoJSON format
            ],
            'impact' => [
                'estimated_affected' => $report->estimated_affected,
                'weather_condition' => $report->weather_condition,
            ],
            'timeline' => [
                'incident_timestamp' => $report->incident_timestamp->toISOString(),
                'reported_at' => $report->created_at->toISOString(),
                'last_updated' => $report->updated_at->toISOString(),
                'time_since_incident' => $report->incident_timestamp->diffForHumans(),
                'time_since_reported' => $report->created_at->diffForHumans(),
            ],
            'reporter_info' => [
                'id' => $report->reporter->id ?? null,
                'name' => $report->reporter->name ?? 'Unknown Reporter',
                'email' => $report->reporter->email ?? 'N/A',
                'phone' => $report->reporter->phone ?? 'N/A',
                'organization' => $report->reporter->organization ?? 'Individual',
            ],
            'media' => [
                'images' => $report->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->image_path,
                        'thumbnail_url' => $this->generateThumbnailUrl($image->image_path),
                        'is_primary' => $image->is_primary,
                        'uploaded_at' => $image->created_at->toISOString(),
                    ];
                })->toArray(),
                'image_count' => $report->images->count(),
                'has_media' => $report->images->count() > 0,
            ],
            'admin_info' => [
                'needs_attention' => $this->needsAttention($report),
                'priority_score' => $this->calculatePriorityScore($report),
                'verification_status' => $this->getVerificationStatus($report),
                'assigned_team' => $report->assigned_to ?? null,
            ],
            'metadata' => $this->extractWebMetadata($report->metadata ?? [])
        ];
    }

    /**
     * Standardize disaster types across platforms
     */
    private function mapDisasterType(string $type): string
    {
        $typeMap = [
            // Mobile variations
            'earthquake' => 'EARTHQUAKE',
            'flood' => 'FLOOD',
            'fire' => 'FIRE',
            'hurricane' => 'HURRICANE',
            'tsunami' => 'TSUNAMI',
            'landslide' => 'LANDSLIDE',
            'volcano' => 'VOLCANO',
            'drought' => 'DROUGHT',
            'blizzard' => 'BLIZZARD',
            'tornado' => 'TORNADO',
            
            // Web variations
            'gempa_bumi' => 'EARTHQUAKE',
            'banjir' => 'FLOOD',
            'kebakaran' => 'FIRE',
            'badai' => 'HURRICANE',
            'gunung_meletus' => 'VOLCANO',
            'tanah_longsor' => 'LANDSLIDE',
            'kekeringan' => 'DROUGHT',
            'other' => 'OTHER',
            'lainnya' => 'OTHER',
        ];

        return $typeMap[strtolower($type)] ?? 'OTHER';
    }

    /**
     * Standardize severity levels across platforms
     */
    private function mapSeverityLevel(string $severity): string
    {
        $severityMap = [
            // English variations
            'low' => 'LOW',
            'medium' => 'MEDIUM',
            'high' => 'HIGH',
            'critical' => 'CRITICAL',
            
            // Numeric scale (1-4)
            '1' => 'LOW',
            '2' => 'MEDIUM',
            '3' => 'HIGH',
            '4' => 'CRITICAL',
            
            // Indonesian variations
            'rendah' => 'LOW',
            'sedang' => 'MEDIUM',
            'tinggi' => 'HIGH',
            'kritis' => 'CRITICAL',
        ];

        return $severityMap[strtolower($severity)] ?? 'MEDIUM';
    }

    /**
     * Validate coordinate values
     */
    private function validateCoordinate(float $coordinate, string $type): float
    {
        if ($type === 'latitude') {
            return max(-90, min(90, $coordinate));
        } else {
            return max(-180, min(180, $coordinate));
        }
    }

    /**
     * Sanitize integer values
     */
    private function sanitizeInteger($value): int
    {
        return max(0, (int) $value);
    }

    /**
     * Validate and format timestamp
     */
    private function validateTimestamp($timestamp): string
    {
        if ($timestamp instanceof \DateTime) {
            return $timestamp->format('Y-m-d H:i:s');
        }
        
        if (is_string($timestamp)) {
            try {
                return Carbon::parse($timestamp)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return now()->format('Y-m-d H:i:s');
            }
        }
        
        return now()->format('Y-m-d H:i:s');
    }

    /**
     * Build metadata for mobile submissions
     */
    private function buildMobileMetadata(array $data): array
    {
        return [
            'source' => 'mobile_app',
            'platform' => 'flutter',
            'app_version' => $data['app_version'] ?? 'unknown',
            'device_info' => $data['device_info'] ?? [],
            'location_accuracy' => $data['location_accuracy'] ?? null,
            'network_type' => $data['network_type'] ?? 'unknown',
            'submission_method' => 'mobile_form',
            'original_data' => $data,
            'processed_at' => now()->toISOString(),
        ];
    }

    /**
     * Build metadata for web submissions
     */
    private function buildWebMetadata(array $data): array
    {
        return [
            'source' => 'web_dashboard',
            'platform' => 'web',
            'browser_info' => $data['browser_info'] ?? 'unknown',
            'user_agent' => request()->header('User-Agent'),
            'ip_address' => request()->ip(),
            'submission_method' => 'web_form',
            'reporter_contact' => $data['reporter_contact'] ?? null,
            'emergency_level' => $data['emergency_level'] ?? null,
            'original_data' => $data,
            'processed_at' => now()->toISOString(),
        ];
    }

    /**
     * Extract mobile-specific metadata
     */
    private function extractMobileMetadata(array $metadata): array
    {
        return [
            'source' => $metadata['source'] ?? 'unknown',
            'app_version' => $metadata['app_version'] ?? null,
            'location_accuracy' => $metadata['location_accuracy'] ?? null,
            'network_type' => $metadata['network_type'] ?? null,
        ];
    }

    /**
     * Extract web-specific metadata
     */
    private function extractWebMetadata(array $metadata): array
    {
        return [
            'source' => $metadata['source'] ?? 'unknown',
            'submission_platform' => $metadata['platform'] ?? 'unknown',
            'reporter_contact' => $metadata['reporter_contact'] ?? null,
            'emergency_level' => $metadata['emergency_level'] ?? null,
            'verification' => $metadata['verification'] ?? null,
            'publication' => $metadata['publication'] ?? null,
        ];
    }

    /**
     * Get human-readable disaster type labels
     */
    private function getDisasterTypeLabel(string $type): string
    {
        $labels = [
            'EARTHQUAKE' => 'Earthquake',
            'FLOOD' => 'Flood',
            'FIRE' => 'Fire',
            'HURRICANE' => 'Hurricane',
            'TSUNAMI' => 'Tsunami',
            'LANDSLIDE' => 'Landslide',
            'VOLCANO' => 'Volcanic Eruption',
            'DROUGHT' => 'Drought',
            'BLIZZARD' => 'Blizzard',
            'TORNADO' => 'Tornado',
            'OTHER' => 'Other',
        ];

        return $labels[$type] ?? 'Unknown';
    }

    /**
     * Get human-readable severity labels and colors
     */
    private function getSeverityLabel(string $severity): string
    {
        $labels = [
            'LOW' => 'Low',
            'MEDIUM' => 'Medium',
            'HIGH' => 'High',
            'CRITICAL' => 'Critical',
        ];

        return $labels[$severity] ?? 'Unknown';
    }

    private function getSeverityColor(string $severity): string
    {
        $colors = [
            'LOW' => '#28a745',      // Green
            'MEDIUM' => '#ffc107',   // Yellow
            'HIGH' => '#fd7e14',     // Orange
            'CRITICAL' => '#dc3545', // Red
        ];

        return $colors[$severity] ?? '#6c757d';
    }

    /**
     * Get status labels and colors
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'PENDING' => 'Pending Review',
            'VERIFIED' => 'Verified',
            'PUBLISHED' => 'Published',
            'RESOLVED' => 'Resolved',
            'REJECTED' => 'Rejected',
            'IN_PROGRESS' => 'In Progress',
        ];

        return $labels[$status] ?? 'Unknown';
    }

    private function getStatusColor(string $status): string
    {
        $colors = [
            'PENDING' => '#ffc107',    // Yellow
            'VERIFIED' => '#17a2b8',   // Cyan
            'PUBLISHED' => '#28a745',  // Green
            'RESOLVED' => '#6f42c1',   // Purple
            'REJECTED' => '#dc3545',   // Red
            'IN_PROGRESS' => '#fd7e14', // Orange
        ];

        return $colors[$status] ?? '#6c757d';
    }

    /**
     * Generate thumbnail URL for images
     */
    private function generateThumbnailUrl(string $imageUrl): string
    {
        // Simple thumbnail generation logic
        // In production, use a proper image processing service
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return $imageUrl; // For now, return original URL
        }
        
        return asset('storage/thumbnails/' . basename($imageUrl));
    }

    /**
     * Determine if report needs attention
     */
    private function needsAttention(object $report): bool
    {
        return in_array($report->status, ['PENDING', 'CRITICAL']) ||
               in_array($report->severity_level, ['HIGH', 'CRITICAL']) ||
               $report->created_at->diffInHours(now()) > 24;
    }

    /**
     * Calculate priority score for sorting
     */
    private function calculatePriorityScore(object $report): int
    {
        $score = 0;
        
        // Severity weight
        $severityWeights = [
            'CRITICAL' => 40,
            'HIGH' => 30,
            'MEDIUM' => 20,
            'LOW' => 10,
        ];
        $score += $severityWeights[$report->severity_level] ?? 0;
        
        // Status weight
        $statusWeights = [
            'PENDING' => 20,
            'VERIFIED' => 15,
            'IN_PROGRESS' => 10,
            'PUBLISHED' => 5,
            'RESOLVED' => 1,
        ];
        $score += $statusWeights[$report->status] ?? 0;
        
        // Time urgency (more points for older reports)
        $hoursOld = $report->created_at->diffInHours(now());
        if ($hoursOld > 48) $score += 20;
        elseif ($hoursOld > 24) $score += 15;
        elseif ($hoursOld > 12) $score += 10;
        elseif ($hoursOld > 6) $score += 5;
        
        return $score;
    }

    /**
     * Get verification status info
     */
    private function getVerificationStatus(object $report): array
    {
        $metadata = $report->metadata ?? [];
        $verification = $metadata['verification'] ?? null;
        
        if (!$verification) {
            return [
                'is_verified' => false,
                'verified_by' => null,
                'verified_at' => null,
                'notes' => null,
            ];
        }
        
        return [
            'is_verified' => true,
            'verified_by' => $verification['verified_by'] ?? null,
            'verified_at' => $verification['verified_at'] ?? null,
            'notes' => $verification['verification_notes'] ?? null,
        ];
    }
}
