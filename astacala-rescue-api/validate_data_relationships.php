<?php

/**
 * Data Relationships Validation Script
 * INTEGRATION_ROADMAP.md Phase 3 Week 4 Database Unification
 * 
 * This script validates all data relationships after the migration
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔗 Validating Data Relationships After Migration\n";
echo "=================================================\n\n";

class DataRelationshipValidator
{
    private $results = [];

    public function validate()
    {
        echo "🔍 Starting comprehensive data relationship validation...\n\n";

        $this->validateUserReferences();
        $this->validateReportImageRelationships();
        $this->validateNotificationRelationships();
        $this->validatePublicationRelationships();
        $this->validateForumMessageRelationships();
        $this->validateOrphanedRecords();
        $this->validateDataConsistency();

        $this->printSummary();

        return $this->allValidationsPassed();
    }

    private function validateUserReferences()
    {
        echo "👥 Validating user references...\n";

        // Check disaster_reports.reported_by references
        $invalidReportedBy = DB::table('disaster_reports')
            ->leftJoin('users', 'disaster_reports.reported_by', '=', 'users.id')
            ->whereNotNull('disaster_reports.reported_by')
            ->whereNull('users.id')
            ->count();

        $this->results['user_reported_by'] = [
            'status' => $invalidReportedBy === 0 ? 'PASS' : 'FAIL',
            'message' => $invalidReportedBy === 0
                ? 'All reported_by references are valid'
                : "{$invalidReportedBy} invalid reported_by references found"
        ];

        // Check disaster_reports.assigned_to references
        $invalidAssignedTo = DB::table('disaster_reports')
            ->leftJoin('users', 'disaster_reports.assigned_to', '=', 'users.id')
            ->whereNotNull('disaster_reports.assigned_to')
            ->whereNull('users.id')
            ->count();

        $this->results['user_assigned_to'] = [
            'status' => $invalidAssignedTo === 0 ? 'PASS' : 'FAIL',
            'message' => $invalidAssignedTo === 0
                ? 'All assigned_to references are valid'
                : "{$invalidAssignedTo} invalid assigned_to references found"
        ];

        // Check disaster_reports.verified_by_admin_id references
        $invalidVerifiedBy = DB::table('disaster_reports')
            ->leftJoin('users', 'disaster_reports.verified_by_admin_id', '=', 'users.id')
            ->whereNotNull('disaster_reports.verified_by_admin_id')
            ->whereNull('users.id')
            ->count();

        $this->results['user_verified_by'] = [
            'status' => $invalidVerifiedBy === 0 ? 'PASS' : 'FAIL',
            'message' => $invalidVerifiedBy === 0
                ? 'All verified_by_admin_id references are valid'
                : "{$invalidVerifiedBy} invalid verified_by_admin_id references found"
        ];

        echo "  ✅ User reference validation completed\n";
    }

    private function validateReportImageRelationships()
    {
        echo "🖼️ Validating report image relationships...\n";

        $invalidReportImages = DB::table('report_images')
            ->leftJoin('disaster_reports', 'report_images.disaster_report_id', '=', 'disaster_reports.id')
            ->whereNull('disaster_reports.id')
            ->count();

        $this->results['report_images'] = [
            'status' => $invalidReportImages === 0 ? 'PASS' : 'FAIL',
            'message' => $invalidReportImages === 0
                ? 'All report image relationships are valid'
                : "{$invalidReportImages} orphaned report images found"
        ];

        echo "  ✅ Report image validation completed\n";
    }

    private function validateNotificationRelationships()
    {
        echo "🔔 Validating notification relationships...\n";

        // Check notifications.user_id references
        $invalidNotificationUsers = DB::table('notifications')
            ->leftJoin('users', 'notifications.user_id', '=', 'users.id')
            ->whereNotNull('notifications.user_id')
            ->whereNull('users.id')
            ->count();

        $this->results['notification_users'] = [
            'status' => $invalidNotificationUsers === 0 ? 'PASS' : 'FAIL',
            'message' => $invalidNotificationUsers === 0
                ? 'All notification user references are valid'
                : "{$invalidNotificationUsers} invalid notification user references found"
        ];

        echo "  ✅ Notification validation completed\n";
    }

    private function validatePublicationRelationships()
    {
        echo "📰 Validating publication relationships...\n";

        // Check publications.author_id references
        $invalidPublicationAuthors = DB::table('publications')
            ->leftJoin('users', 'publications.author_id', '=', 'users.id')
            ->whereNotNull('publications.author_id')
            ->whereNull('users.id')
            ->count();

        $this->results['publication_authors'] = [
            'status' => $invalidPublicationAuthors === 0 ? 'PASS' : 'FAIL',
            'message' => $invalidPublicationAuthors === 0
                ? 'All publication author references are valid'
                : "{$invalidPublicationAuthors} invalid publication author references found"
        ];

        // Check publication_comments relationships
        $invalidPublicationComments = DB::table('publication_comments')
            ->leftJoin('publications', 'publication_comments.publication_id', '=', 'publications.id')
            ->leftJoin('users', 'publication_comments.user_id', '=', 'users.id')
            ->where(function ($query) {
                $query->whereNull('publications.id')
                    ->orWhereNull('users.id');
            })
            ->count();

        $this->results['publication_comments'] = [
            'status' => $invalidPublicationComments === 0 ? 'PASS' : 'FAIL',
            'message' => $invalidPublicationComments === 0
                ? 'All publication comment relationships are valid'
                : "{$invalidPublicationComments} invalid publication comment relationships found"
        ];

        echo "  ✅ Publication validation completed\n";
    }

    private function validateForumMessageRelationships()
    {
        echo "💬 Validating forum message relationships...\n";

        $invalidForumMessages = DB::table('forum_messages')
            ->leftJoin('users', 'forum_messages.user_id', '=', 'users.id')
            ->whereNull('users.id')
            ->count();

        $this->results['forum_messages'] = [
            'status' => $invalidForumMessages === 0 ? 'PASS' : 'FAIL',
            'message' => $invalidForumMessages === 0
                ? 'All forum message user references are valid'
                : "{$invalidForumMessages} invalid forum message user references found"
        ];

        echo "  ✅ Forum message validation completed\n";
    }

    private function validateOrphanedRecords()
    {
        echo "🔍 Checking for orphaned records...\n";

        $orphanCounts = [];

        // Check for reports without users
        $orphanCounts['reports_without_reporters'] = DB::table('disaster_reports')
            ->whereNotNull('reported_by')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'disaster_reports.reported_by');
            })
            ->count();

        // Check for images without reports
        $orphanCounts['images_without_reports'] = DB::table('report_images')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('disaster_reports')
                    ->whereColumn('disaster_reports.id', 'report_images.disaster_report_id');
            })
            ->count();

        $totalOrphans = array_sum($orphanCounts);

        $this->results['orphaned_records'] = [
            'status' => $totalOrphans === 0 ? 'PASS' : 'WARNING',
            'message' => $totalOrphans === 0
                ? 'No orphaned records found'
                : "Found orphaned records: " . json_encode($orphanCounts)
        ];

        echo "  ✅ Orphaned records check completed\n";
    }

    private function validateDataConsistency()
    {
        echo "🔄 Validating data consistency...\n";

        $inconsistencies = [];

        // Check for reports with casualty_count > estimated_affected
        $casualtyInconsistency = DB::table('disaster_reports')
            ->whereNotNull('casualty_count')
            ->whereNotNull('estimated_affected')
            ->whereColumn('casualty_count', '>', 'estimated_affected')
            ->count();

        if ($casualtyInconsistency > 0) {
            $inconsistencies[] = "{$casualtyInconsistency} reports with casualty_count > estimated_affected";
        }

        // Check for verified reports without verification date
        $verificationInconsistency = DB::table('disaster_reports')
            ->whereNotNull('verified_by_admin_id')
            ->whereNull('verified_at')
            ->count();

        if ($verificationInconsistency > 0) {
            $inconsistencies[] = "{$verificationInconsistency} verified reports without verification date";
        }

        // Check for notification inconsistencies
        $notificationInconsistency = DB::table('disaster_reports')
            ->where('verification_status', true)
            ->where('notification_status', false)
            ->count();

        if ($notificationInconsistency > 0) {
            $inconsistencies[] = "{$notificationInconsistency} verified reports not marked as notified";
        }

        $this->results['data_consistency'] = [
            'status' => empty($inconsistencies) ? 'PASS' : 'WARNING',
            'message' => empty($inconsistencies)
                ? 'No data consistency issues found'
                : 'Consistency issues: ' . implode('; ', $inconsistencies)
        ];

        echo "  ✅ Data consistency validation completed\n";
    }

    private function printSummary()
    {
        echo "\n📊 DATA RELATIONSHIP VALIDATION SUMMARY\n";
        echo "=======================================\n";

        $passCount = 0;
        $warningCount = 0;
        $failCount = 0;
        $totalCount = count($this->results);

        foreach ($this->results as $test => $result) {
            $icon = $this->getStatusIcon($result['status']);
            echo "{$icon} {$test}: {$result['status']} - {$result['message']}\n";

            switch ($result['status']) {
                case 'PASS':
                    $passCount++;
                    break;
                case 'WARNING':
                    $warningCount++;
                    break;
                case 'FAIL':
                    $failCount++;
                    break;
            }
        }

        echo "\n📈 Results: {$passCount} passed, {$warningCount} warnings, {$failCount} failed (Total: {$totalCount})\n\n";

        if ($this->allValidationsPassed()) {
            echo "🎉 All critical data relationships validated successfully!\n";
        } else {
            echo "⚠️ Some validations failed. Review issues before proceeding.\n";
        }
    }

    private function getStatusIcon($status)
    {
        switch ($status) {
            case 'PASS':
                return '✅';
            case 'FAIL':
                return '❌';
            case 'WARNING':
                return '⚠️';
            default:
                return '❓';
        }
    }

    private function allValidationsPassed()
    {
        foreach ($this->results as $result) {
            if ($result['status'] === 'FAIL') {
                return false;
            }
        }
        return true;
    }
}

try {
    $validator = new DataRelationshipValidator();
    $success = $validator->validate();

    if ($success) {
        echo "\n📋 Ready for next INTEGRATION_ROADMAP.md step:\n";
        echo "✅ Validate all data relationships - COMPLETED\n";
        echo "[ ] Test both mobile and web apps with unified data\n";
        echo "[ ] Monitor system performance post-migration\n";
    }

    exit($success ? 0 : 1);
} catch (Exception $e) {
    echo "❌ Validation failed: " . $e->getMessage() . "\n";
    exit(1);
}
