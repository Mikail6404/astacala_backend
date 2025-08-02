<?php

namespace App\Services;

use App\Models\DisasterReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class DataValidationService
{
    /**
     * Comprehensive validation rules for disaster reports
     */
    protected $disasterReportRules = [
        'title' => 'required|string|min:5|max:200',
        'description' => 'required|string|min:10|max:2000',
        'disaster_type' => 'required|in:earthquake,flood,fire,hurricane,tornado,drought,landslide,tsunami,volcanic_eruption,other',
        'severity_level' => 'required|in:low,medium,high,critical',
        'status' => 'required|in:pending,verified,false_alarm,resolved',
        'latitude' => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'location_name' => 'required|string|min:3|max:255',
        'reported_by' => 'required|exists:users,id',
        'incident_timestamp' => 'nullable|date|before_or_equal:now',
        'estimated_affected' => 'nullable|integer|min:0|max:1000000',
        'casualty_count' => 'nullable|integer|min:0|max:100000',
        'weather_condition' => 'nullable|string|max:100',
        'personnel_count' => 'nullable|integer|min:0|max:1000',
        'contact_phone' => 'nullable|string|regex:/^[\+]?[0-9\s\-\(\)]+$/|max:20',
        'scale_assessment' => 'nullable|string|in:minor,moderate,major,catastrophic',
        'verification_status' => 'nullable|in:unverified,verified,disputed,investigating'
    ];

    /**
     * Data consistency rules for cross-platform validation
     */
    protected $consistencyRules = [
        'location_consistency' => [
            'latitude_longitude_match' => true,
            'location_name_coordinate_proximity' => true,
            'address_coordinate_alignment' => true
        ],
        'severity_consistency' => [
            'casualty_severity_alignment' => true,
            'affected_severity_alignment' => true,
            'scale_severity_alignment' => true
        ],
        'timestamp_consistency' => [
            'incident_before_report' => true,
            'verification_after_incident' => true,
            'modification_tracking' => true
        ]
    ];

    /**
     * Validate disaster report data with comprehensive checks
     */
    public function validateDisasterReport($data, $isUpdate = false)
    {
        $rules = $this->disasterReportRules;

        // Adjust rules for updates
        if ($isUpdate) {
            $rules = array_map(function ($rule) {
                return str_replace('required|', 'sometimes|required|', $rule);
            }, $rules);
        }

        $validator = Validator::make($data, $rules);

        // Add custom validation messages
        $validator->setCustomMessages([
            'title.min' => 'Report title must be at least 5 characters long',
            'description.min' => 'Description must be at least 10 characters long',
            'latitude.between' => 'Latitude must be between -90 and 90 degrees',
            'longitude.between' => 'Longitude must be between -180 and 180 degrees',
            'disaster_type.in' => 'Invalid disaster type selected',
            'severity_level.in' => 'Invalid severity level selected',
            'status.in' => 'Invalid status selected',
            'contact_phone.regex' => 'Phone number format is invalid'
        ]);

        // Custom validation rules
        $validator->after(function ($validator) use ($data) {
            $this->validateDataConsistency($validator, $data);
        });

        return $validator;
    }

    /**
     * Validate data consistency across fields
     */
    protected function validateDataConsistency($validator, $data)
    {
        // Severity and casualty consistency
        if (isset($data['severity_level'], $data['casualty_count'])) {
            if ($data['severity_level'] === 'low' && $data['casualty_count'] > 5) {
                $validator->errors()->add('casualty_count', 'Casualty count too high for low severity incident');
            }
            if ($data['severity_level'] === 'critical' && $data['casualty_count'] == 0) {
                $validator->errors()->add('severity_level', 'Critical incidents typically involve casualties');
            }
        }

        // Severity and affected people consistency
        if (isset($data['severity_level'], $data['estimated_affected'])) {
            if ($data['severity_level'] === 'low' && $data['estimated_affected'] > 100) {
                $validator->errors()->add('estimated_affected', 'Too many affected people for low severity');
            }
            if ($data['severity_level'] === 'critical' && $data['estimated_affected'] < 10) {
                $validator->errors()->add('estimated_affected', 'Critical incidents typically affect more people');
            }
        }

        // Timestamp consistency
        if (isset($data['incident_timestamp'])) {
            $incidentTime = Carbon::parse($data['incident_timestamp']);
            if ($incidentTime->isFuture()) {
                $validator->errors()->add('incident_timestamp', 'Incident cannot be in the future');
            }
            if ($incidentTime->diffInDays(now()) > 365) {
                $validator->errors()->add('incident_timestamp', 'Incident timestamp seems too old (over 1 year)');
            }
        }

        // Location consistency (basic check)
        if (isset($data['latitude'], $data['longitude'])) {
            // Check if coordinates are in a valid range for real locations
            $lat = $data['latitude'];
            $lng = $data['longitude'];

            // Basic sanity checks for impossible coordinates
            if ($lat == 0 && $lng == 0) {
                $validator->errors()->add('location', 'Coordinates appear to be default values (0,0)');
            }
        }

        // Disaster type and severity alignment
        if (isset($data['disaster_type'], $data['severity_level'])) {
            $this->validateDisasterTypeSeverityAlignment($validator, $data['disaster_type'], $data['severity_level']);
        }
    }

    /**
     * Validate disaster type and severity alignment
     */
    protected function validateDisasterTypeSeverityAlignment($validator, $disasterType, $severityLevel)
    {
        $typicalSeverities = [
            'earthquake' => ['medium', 'high', 'critical'],
            'tsunami' => ['high', 'critical'],
            'volcanic_eruption' => ['high', 'critical'],
            'hurricane' => ['medium', 'high', 'critical'],
            'tornado' => ['medium', 'high', 'critical'],
            'flood' => ['low', 'medium', 'high', 'critical'],
            'fire' => ['low', 'medium', 'high'],
            'drought' => ['low', 'medium', 'high'],
            'landslide' => ['medium', 'high']
        ];

        if (isset($typicalSeverities[$disasterType])) {
            if (!in_array($severityLevel, $typicalSeverities[$disasterType])) {
                $validator->errors()->add(
                    'severity_level',
                    "Severity level '{$severityLevel}' is unusual for {$disasterType}. Please verify."
                );
            }
        }
    }

    /**
     * Perform comprehensive data health check
     */
    public function performDataHealthCheck()
    {
        $healthReport = [
            'timestamp' => now()->toISOString(),
            'checks_performed' => [],
            'issues_found' => [],
            'recommendations' => [],
            'overall_status' => 'healthy'
        ];

        // Check 1: Orphaned records
        $orphanedReports = $this->checkOrphanedReports();
        $healthReport['checks_performed'][] = 'orphaned_reports';
        if ($orphanedReports['count'] > 0) {
            $healthReport['issues_found'][] = $orphanedReports;
            $healthReport['overall_status'] = 'issues_found';
        }

        // Check 2: Data consistency violations
        $consistencyIssues = $this->checkDataConsistency();
        $healthReport['checks_performed'][] = 'data_consistency';
        if (!empty($consistencyIssues)) {
            $healthReport['issues_found'] = array_merge($healthReport['issues_found'], $consistencyIssues);
            $healthReport['overall_status'] = 'issues_found';
        }

        // Check 3: Duplicate reports
        $duplicateReports = $this->checkDuplicateReports();
        $healthReport['checks_performed'][] = 'duplicate_reports';
        if ($duplicateReports['count'] > 0) {
            $healthReport['issues_found'][] = $duplicateReports;
            $healthReport['overall_status'] = 'issues_found';
        }

        // Check 4: Version control integrity
        $versionIssues = $this->checkVersionIntegrity();
        $healthReport['checks_performed'][] = 'version_integrity';
        if ($versionIssues['count'] > 0) {
            $healthReport['issues_found'][] = $versionIssues;
            $healthReport['overall_status'] = 'issues_found';
        }

        // Check 5: Audit trail completeness
        $auditIssues = $this->checkAuditTrailCompleteness();
        $healthReport['checks_performed'][] = 'audit_trail_completeness';
        if ($auditIssues['count'] > 0) {
            $healthReport['issues_found'][] = $auditIssues;
            $healthReport['overall_status'] = 'issues_found';
        }

        // Generate recommendations
        $healthReport['recommendations'] = $this->generateRecommendations($healthReport['issues_found']);

        return $healthReport;
    }

    /**
     * Check for orphaned disaster reports
     */
    protected function checkOrphanedReports()
    {
        $orphanedCount = DB::table('disaster_reports')
            ->leftJoin('users', 'disaster_reports.reported_by', '=', 'users.id')
            ->whereNull('users.id')
            ->count();

        $orphanedIds = DB::table('disaster_reports')
            ->leftJoin('users', 'disaster_reports.reported_by', '=', 'users.id')
            ->whereNull('users.id')
            ->pluck('disaster_reports.id')
            ->toArray();

        return [
            'type' => 'orphaned_reports',
            'count' => $orphanedCount,
            'report_ids' => $orphanedIds,
            'description' => 'Reports with invalid user references',
            'severity' => $orphanedCount > 0 ? 'medium' : 'none'
        ];
    }

    /**
     * Check data consistency across the system
     */
    protected function checkDataConsistency()
    {
        $issues = [];

        // Check for reports with impossible coordinates
        $invalidCoordinates = DB::table('disaster_reports')
            ->where(function ($query) {
                $query->where('latitude', 0)->where('longitude', 0);
            })
            ->orWhere('latitude', '>', 90)
            ->orWhere('latitude', '<', -90)
            ->orWhere('longitude', '>', 180)
            ->orWhere('longitude', '<', -180)
            ->count();

        if ($invalidCoordinates > 0) {
            $issues[] = [
                'type' => 'invalid_coordinates',
                'count' => $invalidCoordinates,
                'description' => 'Reports with invalid latitude/longitude values',
                'severity' => 'high'
            ];
        }

        // Check for severity-casualty mismatches
        $severityMismatches = DB::table('disaster_reports')
            ->where(function ($query) {
                $query->where('severity_level', 'low')->where('casualty_count', '>', 5);
            })
            ->orWhere(function ($query) {
                $query->where('severity_level', 'critical')->where('casualty_count', 0);
            })
            ->count();

        if ($severityMismatches > 0) {
            $issues[] = [
                'type' => 'severity_casualty_mismatch',
                'count' => $severityMismatches,
                'description' => 'Reports with inconsistent severity and casualty data',
                'severity' => 'medium'
            ];
        }

        // Check for future incident timestamps
        $futureIncidents = DB::table('disaster_reports')
            ->where('incident_timestamp', '>', now())
            ->count();

        if ($futureIncidents > 0) {
            $issues[] = [
                'type' => 'future_incidents',
                'count' => $futureIncidents,
                'description' => 'Reports with incident timestamps in the future',
                'severity' => 'high'
            ];
        }

        return $issues;
    }

    /**
     * Check for duplicate reports
     */
    protected function checkDuplicateReports()
    {
        // Find reports with similar coordinates (within 1km) and same disaster type
        $duplicates = DB::select("
            SELECT 
                r1.id as report1_id,
                r2.id as report2_id,
                r1.title as report1_title,
                r2.title as report2_title,
                r1.disaster_type,
                (6371 * acos(cos(radians(r1.latitude)) * cos(radians(r2.latitude)) * 
                cos(radians(r2.longitude) - radians(r1.longitude)) + 
                sin(radians(r1.latitude)) * sin(radians(r2.latitude)))) AS distance_km
            FROM disaster_reports r1
            JOIN disaster_reports r2 ON r1.id < r2.id
            WHERE r1.disaster_type = r2.disaster_type
            AND ABS(TIMESTAMPDIFF(HOUR, r1.incident_timestamp, r2.incident_timestamp)) <= 24
            HAVING distance_km < 1
        ");

        return [
            'type' => 'duplicate_reports',
            'count' => count($duplicates),
            'duplicates' => $duplicates,
            'description' => 'Potentially duplicate reports within 1km and 24 hours',
            'severity' => count($duplicates) > 0 ? 'medium' : 'none'
        ];
    }

    /**
     * Check version control integrity
     */
    protected function checkVersionIntegrity()
    {
        // Check for reports with version mismatches or missing versions
        $versionIssues = DB::table('disaster_reports')
            ->where(function ($query) {
                $query->whereNull('version')->orWhere('version', '<', 1);
            })
            ->count();

        $versionInconsistencies = DB::table('disaster_reports')
            ->where('version', '>', 10) // Unusually high version numbers
            ->count();

        $totalIssues = $versionIssues + $versionInconsistencies;

        return [
            'type' => 'version_integrity',
            'count' => $totalIssues,
            'missing_versions' => $versionIssues,
            'high_versions' => $versionInconsistencies,
            'description' => 'Reports with version control issues',
            'severity' => $totalIssues > 0 ? 'medium' : 'none'
        ];
    }

    /**
     * Check audit trail completeness
     */
    protected function checkAuditTrailCompleteness()
    {
        // Check for recent modifications without audit trails
        $reportsWithoutAudit = DB::table('disaster_reports')
            ->leftJoin('disaster_report_audit_trails', 'disaster_reports.id', '=', 'disaster_report_audit_trails.report_id')
            ->where('disaster_reports.updated_at', '>', now()->subDays(7))
            ->whereNull('disaster_report_audit_trails.id')
            ->count();

        return [
            'type' => 'missing_audit_trails',
            'count' => $reportsWithoutAudit,
            'description' => 'Recent modifications without corresponding audit trails',
            'severity' => $reportsWithoutAudit > 0 ? 'high' : 'none'
        ];
    }

    /**
     * Generate recommendations based on found issues
     */
    protected function generateRecommendations($issues)
    {
        $recommendations = [];

        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'orphaned_reports':
                    $recommendations[] = 'Consider cleaning up orphaned reports or assigning them to a system user';
                    break;
                case 'invalid_coordinates':
                    $recommendations[] = 'Implement stricter coordinate validation in forms and APIs';
                    break;
                case 'severity_casualty_mismatch':
                    $recommendations[] = 'Add cross-field validation for severity and casualty data';
                    break;
                case 'future_incidents':
                    $recommendations[] = 'Add timestamp validation to prevent future incident dates';
                    break;
                case 'duplicate_reports':
                    $recommendations[] = 'Implement duplicate detection before report submission';
                    break;
                case 'version_integrity':
                    $recommendations[] = 'Review version control implementation and fix missing versions';
                    break;
                case 'missing_audit_trails':
                    $recommendations[] = 'Ensure all modifications are properly logged in audit trails';
                    break;
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = 'No critical issues found. Continue regular monitoring.';
        }

        return array_unique($recommendations);
    }

    /**
     * Fix common data integrity issues automatically
     */
    public function autoFixDataIssues()
    {
        $fixedIssues = [];

        try {
            DB::beginTransaction();

            // Fix 1: Set default versions for reports without version
            $fixedVersions = DB::table('disaster_reports')
                ->whereNull('version')
                ->update(['version' => 1]);

            if ($fixedVersions > 0) {
                $fixedIssues[] = [
                    'type' => 'fixed_missing_versions',
                    'count' => $fixedVersions,
                    'description' => 'Set default version (1) for reports missing version'
                ];
            }

            // Fix 2: Remove reports with completely invalid coordinates (0,0)
            $invalidReports = DB::table('disaster_reports')
                ->where('latitude', 0)
                ->where('longitude', 0)
                ->where('location_name', 'NOT LIKE', '%test%')
                ->get(['id', 'title']);

            foreach ($invalidReports as $report) {
                // Only auto-fix if it's clearly a test report
                if (stripos($report->title, 'test') !== false) {
                    DB::table('disaster_reports')->where('id', $report->id)->delete();
                    $fixedIssues[] = [
                        'type' => 'removed_test_report',
                        'report_id' => $report->id,
                        'description' => "Removed test report with invalid coordinates: {$report->title}"
                    ];
                }
            }

            // Fix 3: Update last_modified_at for reports that have it null
            $fixedTimestamps = DB::table('disaster_reports')
                ->whereNull('last_modified_at')
                ->update(['last_modified_at' => DB::raw('updated_at')]);

            if ($fixedTimestamps > 0) {
                $fixedIssues[] = [
                    'type' => 'fixed_missing_last_modified',
                    'count' => $fixedTimestamps,
                    'description' => 'Set last_modified_at from updated_at for reports'
                ];
            }

            DB::commit();

            Log::info('Data integrity auto-fix completed', [
                'fixed_issues' => $fixedIssues,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Data integrity auto-fix failed', [
                'error' => $e->getMessage(),
                'timestamp' => now()
            ]);
            throw $e;
        }

        return $fixedIssues;
    }

    /**
     * Generate data validation report
     */
    public function generateValidationReport()
    {
        $healthCheck = $this->performDataHealthCheck();

        $report = [
            'report_timestamp' => now()->toISOString(),
            'system_status' => $healthCheck['overall_status'],
            'total_reports' => DB::table('disaster_reports')->count(),
            'total_users' => DB::table('users')->count(),
            'total_audit_entries' => DB::table('disaster_report_audit_trails')->count(),
            'total_conflicts' => DB::table('conflict_resolution_queue')->count(),
            'health_check' => $healthCheck,
            'summary' => [
                'critical_issues' => collect($healthCheck['issues_found'])->where('severity', 'high')->count(),
                'medium_issues' => collect($healthCheck['issues_found'])->where('severity', 'medium')->count(),
                'low_issues' => collect($healthCheck['issues_found'])->where('severity', 'low')->count(),
                'recommendations_count' => count($healthCheck['recommendations'])
            ]
        ];

        // Log the report
        Log::info('Data validation report generated', $report);

        return $report;
    }
}
