<?php

namespace App\Services;

use App\Models\DisasterReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Cross-Platform Conflict Resolution Service
 * 
 * Handles conflicts when multiple users (mobile volunteers and web admins)
 * attempt to modify the same disaster report simultaneously.
 * 
 * Features:
 * - Optimistic locking for concurrent edits
 * - Version control for disaster reports
 * - Conflict detection and resolution
 * - Audit trail for all modifications
 */
class ConflictResolutionService
{
    /**
     * Conflict resolution strategies
     */
    const STRATEGY_ADMIN_WINS = 'admin_wins';           // Admin changes override volunteer changes
    const STRATEGY_LATEST_WINS = 'latest_wins';         // Most recent change wins
    const STRATEGY_MERGE_FIELDS = 'merge_fields';       // Merge non-conflicting fields
    const STRATEGY_MANUAL_REVIEW = 'manual_review';     // Flag for manual admin review

    /**
     * Fields that can be safely merged without conflicts
     */
    const MERGEABLE_FIELDS = [
        'description',           // Additional details can be appended
        'estimated_affected',    // Can be updated with better estimates
        'weather_condition',     // Can be refined
        'additional_description' // Web-specific additional details
    ];

    /**
     * Fields that require conflict resolution if changed simultaneously
     */
    const CRITICAL_FIELDS = [
        'title',
        'disaster_type',
        'severity_level',
        'latitude',
        'longitude',
        'location_name',
        'status'
    ];

    /**
     * Attempt to update a disaster report with optimistic locking
     *
     * @param int $reportId
     * @param array $newData
     * @param User $user
     * @param string $expectedVersion
     * @return array
     */
    public function updateWithConflictDetection($reportId, $newData, $user, $expectedVersion = null)
    {
        DB::beginTransaction();

        try {
            // Lock the report for update
            $report = DisasterReport::lockForUpdate()->find($reportId);

            if (!$report) {
                throw new \Exception("Disaster report not found: {$reportId}");
            }

            // Check version if provided (optimistic locking)
            if ($expectedVersion && $report->version !== $expectedVersion) {
                return $this->handleVersionConflict($report, $newData, $user, $expectedVersion);
            }

            // Check for concurrent modifications
            $conflictCheck = $this->detectConcurrentModifications($report, $newData, $user);

            if ($conflictCheck['has_conflict']) {
                return $this->resolveConflict($report, $newData, $user, $conflictCheck);
            }

            // No conflicts detected, proceed with update
            $result = $this->performUpdate($report, $newData, $user);

            DB::commit();

            Log::info("Report updated successfully without conflicts", [
                'report_id' => $reportId,
                'user_id' => $user->id,
                'user_platform' => $this->getUserPlatform($user),
                'updated_fields' => array_keys($newData)
            ]);

            return [
                'success' => true,
                'message' => 'Report updated successfully',
                'data' => $result,
                'conflicts' => false
            ];
        } catch (\Exception $e) {
            DB::rollback();

            Log::error("Update failed with error", [
                'report_id' => $reportId,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
                'conflicts' => false
            ];
        }
    }

    /**
     * Detect concurrent modifications to the same report
     *
     * @param DisasterReport $report
     * @param array $newData
     * @param User $user
     * @return array
     */
    protected function detectConcurrentModifications($report, $newData, $user)
    {
        // Check if report was modified by another user in the last 5 minutes
        $recentModification = $this->getRecentModifications($report->id, 5);

        if (!$recentModification) {
            return ['has_conflict' => false];
        }

        // Exclude modifications by the same user
        if ($recentModification->modified_by === $user->id) {
            return ['has_conflict' => false];
        }

        // Check which fields are being modified by both users
        $conflictingFields = $this->findConflictingFields(
            $recentModification->modified_fields,
            array_keys($newData)
        );

        if (empty($conflictingFields)) {
            return ['has_conflict' => false];
        }

        return [
            'has_conflict' => true,
            'recent_modification' => $recentModification,
            'conflicting_fields' => $conflictingFields,
            'conflict_severity' => $this->assessConflictSeverity($conflictingFields)
        ];
    }

    /**
     * Resolve conflicts based on configured strategy
     *
     * @param DisasterReport $report
     * @param array $newData
     * @param User $user
     * @param array $conflictInfo
     * @return array
     */
    protected function resolveConflict($report, $newData, $user, $conflictInfo)
    {
        $strategy = $this->determineResolutionStrategy($user, $conflictInfo);

        switch ($strategy) {
            case self::STRATEGY_ADMIN_WINS:
                return $this->resolveAdminWins($report, $newData, $user, $conflictInfo);

            case self::STRATEGY_LATEST_WINS:
                return $this->resolveLatestWins($report, $newData, $user, $conflictInfo);

            case self::STRATEGY_MERGE_FIELDS:
                return $this->resolveMergeFields($report, $newData, $user, $conflictInfo);

            case self::STRATEGY_MANUAL_REVIEW:
            default:
                return $this->flagForManualReview($report, $newData, $user, $conflictInfo);
        }
    }

    /**
     * Admin changes override volunteer changes
     */
    protected function resolveAdminWins($report, $newData, $user, $conflictInfo)
    {
        if ($this->isAdminUser($user)) {
            // Admin user - proceed with update
            $result = $this->performUpdate($report, $newData, $user);

            // Notify the volunteer about the override
            $this->notifyConflictResolution($report, $user, $conflictInfo, 'admin_override');

            return [
                'success' => true,
                'message' => 'Admin changes applied successfully (volunteer changes overridden)',
                'data' => $result,
                'conflicts' => true,
                'resolution' => 'admin_override'
            ];
        } else {
            // Volunteer user - reject update
            return [
                'success' => false,
                'message' => 'Update rejected: Admin has made recent changes to this report',
                'conflicts' => true,
                'resolution' => 'admin_priority',
                'admin_changes' => $this->getAdminChangeSummary($conflictInfo['recent_modification'])
            ];
        }
    }

    /**
     * Latest change wins (timestamp-based)
     */
    protected function resolveLatestWins($report, $newData, $user, $conflictInfo)
    {
        // Always allow the latest change to proceed
        $result = $this->performUpdate($report, $newData, $user);

        // Notify previous modifier about the override
        $this->notifyConflictResolution($report, $user, $conflictInfo, 'latest_wins');

        return [
            'success' => true,
            'message' => 'Update applied successfully (latest changes win)',
            'data' => $result,
            'conflicts' => true,
            'resolution' => 'latest_wins'
        ];
    }

    /**
     * Merge non-conflicting fields
     */
    protected function resolveMergeFields($report, $newData, $user, $conflictInfo)
    {
        $conflictingFields = $conflictInfo['conflicting_fields'];
        $mergeableFields = array_intersect($conflictingFields, self::MERGEABLE_FIELDS);
        $criticalFields = array_intersect($conflictingFields, self::CRITICAL_FIELDS);

        if (!empty($criticalFields)) {
            // Critical fields conflict - flag for manual review
            return $this->flagForManualReview($report, $newData, $user, $conflictInfo);
        }

        // Merge mergeable fields
        $mergedData = $this->performFieldMerge($report, $newData, $mergeableFields);
        $result = $this->performUpdate($report, $mergedData, $user);

        return [
            'success' => true,
            'message' => 'Update applied with field merging',
            'data' => $result,
            'conflicts' => true,
            'resolution' => 'field_merge',
            'merged_fields' => $mergeableFields
        ];
    }

    /**
     * Flag for manual admin review
     */
    protected function flagForManualReview($report, $newData, $user, $conflictInfo)
    {
        // Create conflict resolution record
        $conflictRecord = $this->createConflictRecord($report, $newData, $user, $conflictInfo);

        // Notify admins about the conflict
        $this->notifyAdminsOfConflict($report, $user, $conflictInfo, $conflictRecord);

        return [
            'success' => false,
            'message' => 'Update requires manual review due to conflicts',
            'conflicts' => true,
            'resolution' => 'manual_review_required',
            'conflict_id' => $conflictRecord->id,
            'conflicting_fields' => $conflictInfo['conflicting_fields'],
            'estimated_review_time' => '15-30 minutes'
        ];
    }

    /**
     * Perform the actual update with version increment
     */
    protected function performUpdate($report, $newData, $user)
    {
        // Store original data for audit trail
        $originalData = $report->toArray();

        // Update the report
        $report->update($newData);

        // Increment version for optimistic locking
        $report->increment('version');

        // Create audit trail
        $this->createAuditTrail($report, $originalData, $newData, $user);

        // Refresh to get updated data
        $report->refresh();

        return $report;
    }

    /**
     * Create audit trail for the modification
     */
    protected function createAuditTrail($report, $originalData, $newData, $user)
    {
        $changes = [];

        foreach ($newData as $field => $newValue) {
            $oldValue = $originalData[$field] ?? null;
            if ($oldValue != $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        DB::table('disaster_report_audit_trails')->insert([
            'report_id' => $report->id,
            'modified_by' => $user->id,
            'user_platform' => $this->getUserPlatform($user),
            'modified_fields' => json_encode(array_keys($changes)),
            'field_changes' => json_encode($changes),
            'modification_timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Get recent modifications to a report
     */
    protected function getRecentModifications($reportId, $minutesBack = 5)
    {
        return DB::table('disaster_report_audit_trails')
            ->where('report_id', $reportId)
            ->where('modification_timestamp', '>=', now()->subMinutes($minutesBack))
            ->orderBy('modification_timestamp', 'desc')
            ->first();
    }

    /**
     * Determine if user is admin
     */
    protected function isAdminUser($user)
    {
        return in_array($user->role, ['admin', 'super_admin']) ||
            $this->getUserPlatform($user) === 'web';
    }

    /**
     * Get user platform (mobile/web)
     */
    protected function getUserPlatform($user)
    {
        // Determine platform based on user type or request context
        if (
            request()->header('User-Agent') &&
            str_contains(request()->header('User-Agent'), 'astacala-mobile')
        ) {
            return 'mobile';
        }

        if (auth('sanctum')->check()) {
            return 'mobile';
        }

        return 'web';
    }

    /**
     * Find fields that are being modified simultaneously
     */
    protected function findConflictingFields($recentFields, $currentFields)
    {
        $recentFieldsArray = is_string($recentFields) ?
            json_decode($recentFields, true) : $recentFields;

        return array_intersect($recentFieldsArray, $currentFields);
    }

    /**
     * Assess conflict severity
     */
    protected function assessConflictSeverity($conflictingFields)
    {
        $criticalConflicts = array_intersect($conflictingFields, self::CRITICAL_FIELDS);

        if (!empty($criticalConflicts)) {
            return 'high';
        }

        if (count($conflictingFields) > 3) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Determine resolution strategy based on user and conflict
     */
    protected function determineResolutionStrategy($user, $conflictInfo)
    {
        // High severity conflicts always require manual review
        if ($conflictInfo['conflict_severity'] === 'high') {
            return self::STRATEGY_MANUAL_REVIEW;
        }

        // Admin users get priority for medium conflicts
        if ($this->isAdminUser($user) && $conflictInfo['conflict_severity'] === 'medium') {
            return self::STRATEGY_ADMIN_WINS;
        }

        // Low severity conflicts can be merged
        if ($conflictInfo['conflict_severity'] === 'low') {
            return self::STRATEGY_MERGE_FIELDS;
        }

        // Default to manual review
        return self::STRATEGY_MANUAL_REVIEW;
    }

    /**
     * Create conflict resolution record for manual review
     */
    protected function createConflictRecord($report, $newData, $user, $conflictInfo)
    {
        return DB::table('conflict_resolution_queue')->insertGetId([
            'report_id' => $report->id,
            'conflicting_user_id' => $user->id,
            'original_user_id' => $conflictInfo['recent_modification']->modified_by,
            'conflicting_fields' => json_encode($conflictInfo['conflicting_fields']),
            'proposed_changes' => json_encode($newData),
            'conflict_severity' => $conflictInfo['conflict_severity'],
            'status' => 'pending_review',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Handle version conflicts (optimistic locking)
     */
    protected function handleVersionConflict($report, $newData, $user, $expectedVersion)
    {
        Log::warning("Version conflict detected", [
            'report_id' => $report->id,
            'expected_version' => $expectedVersion,
            'current_version' => $report->version,
            'user_id' => $user->id
        ]);

        return [
            'success' => false,
            'message' => 'Report has been modified by another user. Please refresh and try again.',
            'conflicts' => true,
            'resolution' => 'version_conflict',
            'expected_version' => $expectedVersion,
            'current_version' => $report->version,
            'last_modified_by' => $report->updated_by ?? 'Unknown',
            'last_modified_at' => $report->updated_at
        ];
    }

    /**
     * Notify users about conflict resolution
     */
    protected function notifyConflictResolution($report, $user, $conflictInfo, $resolutionType)
    {
        // Get the original modifier
        $originalModifier = User::find($conflictInfo['recent_modification']->modified_by);

        if ($originalModifier) {
            // Create notification for the original modifier
            $message = $this->getConflictNotificationMessage($resolutionType, $user, $report);

            // Use existing notification service
            $notificationService = app(\App\Services\CrossPlatformNotificationService::class);
            $notificationService->sendNotification(
                $originalModifier,
                'conflict_resolution',
                'Report Conflict Resolved',
                $message,
                ['report_id' => $report->id, 'resolution_type' => $resolutionType]
            );
        }

        Log::info("Conflict resolution notification sent", [
            'report_id' => $report->id,
            'resolution_type' => $resolutionType,
            'original_modifier' => $originalModifier?->id,
            'current_modifier' => $user->id
        ]);
    }

    /**
     * Get admin change summary
     */
    protected function getAdminChangeSummary($recentModification)
    {
        $modifiedFields = is_string($recentModification->modified_fields) ?
            json_decode($recentModification->modified_fields, true) :
            $recentModification->modified_fields;

        return [
            'modified_by' => User::find($recentModification->modified_by)?->name ?? 'Unknown Admin',
            'modified_at' => $recentModification->modification_timestamp,
            'modified_fields' => $modifiedFields,
            'platform' => $recentModification->user_platform ?? 'web'
        ];
    }

    /**
     * Perform field merge for mergeable fields
     */
    protected function performFieldMerge($report, $newData, $mergeableFields)
    {
        $mergedData = $newData;

        foreach ($mergeableFields as $field) {
            if ($field === 'description' || $field === 'additional_description') {
                // Append new description to existing
                $existingValue = $report->{$field} ?? '';
                $newValue = $newData[$field] ?? '';

                if ($existingValue && $newValue && $existingValue !== $newValue) {
                    $mergedData[$field] = $existingValue . "\n\n[Updated: " . now()->format('Y-m-d H:i') . "]\n" . $newValue;
                }
            } else {
                // For other mergeable fields, use the new value
                $mergedData[$field] = $newData[$field];
            }
        }

        return $mergedData;
    }

    /**
     * Notify admins about conflicts requiring manual review
     */
    protected function notifyAdminsOfConflict($report, $user, $conflictInfo, $conflictRecordId)
    {
        // Get all admin users
        $admins = User::where('role', 'admin')
            ->orWhere('role', 'super_admin')
            ->get();

        $notificationService = app(\App\Services\CrossPlatformNotificationService::class);

        foreach ($admins as $admin) {
            $message = "Conflict detected on report #{$report->id} - '{$report->title}'. " .
                "Manual review required for conflicting changes by {$user->name}.";

            $notificationService->sendNotification(
                $admin,
                'conflict_manual_review',
                'Conflict Requires Review',
                $message,
                [
                    'report_id' => $report->id,
                    'conflict_id' => $conflictRecordId,
                    'conflicting_user' => $user->name,
                    'conflict_severity' => $conflictInfo['conflict_severity']
                ]
            );
        }

        Log::info("Admin conflict notifications sent", [
            'report_id' => $report->id,
            'conflict_id' => $conflictRecordId,
            'admin_count' => $admins->count()
        ]);
    }

    /**
     * Get conflict notification message
     */
    protected function getConflictNotificationMessage($resolutionType, $user, $report)
    {
        switch ($resolutionType) {
            case 'admin_override':
                return "Your recent changes to report '{$report->title}' have been overridden by admin {$user->name}.";

            case 'latest_wins':
                return "Your changes to report '{$report->title}' have been overridden by newer changes from {$user->name}.";

            case 'field_merge':
                return "Your changes to report '{$report->title}' have been merged with changes from {$user->name}.";

            default:
                return "There was a conflict with your changes to report '{$report->title}'.";
        }
    }

    /**
     * Public method to detect conflicts for testing
     */
    public function detectConflict($reportId, $currentVersion, $proposedChanges, $userId)
    {
        $report = DisasterReport::find($reportId);
        if (!$report) {
            throw new \Exception("Report not found with ID: $reportId");
        }

        // Check version mismatch (optimistic locking)
        if ($report->version != $currentVersion) {
            // Create conflict entry
            $conflictingFields = array_keys($proposedChanges);

            DB::table('conflict_resolution_queue')->insert([
                'report_id' => $reportId,
                'conflicting_user_id' => $userId,
                'original_user_id' => $report->last_modified_by ?? $report->reported_by,
                'conflicting_fields' => json_encode($conflictingFields),
                'proposed_changes' => json_encode($proposedChanges),
                'original_changes' => json_encode($report->toArray()),
                'conflict_severity' => $this->calculateConflictSeverity($conflictingFields),
                'status' => 'pending_review',
                'expires_at' => now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return true;
        }

        return false;
    }

    /**
     * Get conflicts for a specific report
     */
    public function getConflictsForReport($reportId, $status = null)
    {
        $query = DB::table('conflict_resolution_queue')
            ->where('report_id', $reportId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Public method to resolve conflicts for testing
     */
    public function resolveConflictManually($conflictId, $resolutionAction, $adminId, $notes = null)
    {
        $conflict = DB::table('conflict_resolution_queue')->where('id', $conflictId)->first();

        if (!$conflict) {
            throw new \Exception("Conflict not found with ID: $conflictId");
        }

        $report = DisasterReport::find($conflict->report_id);

        try {
            DB::beginTransaction();

            // Apply resolution based on action
            switch ($resolutionAction) {
                case 'accept_new':
                    $proposedChanges = json_decode($conflict->proposed_changes, true);
                    $this->applyChangesToReport($report, $proposedChanges, $conflict->conflicting_user_id);
                    break;

                case 'keep_original':
                    // No changes needed - keep current state
                    break;

                case 'merge_changes':
                    $proposedChanges = json_decode($conflict->proposed_changes, true);
                    $conflictingFields = json_decode($conflict->conflicting_fields, true);
                    $mergedData = $this->performFieldMerge($report, $proposedChanges, $conflictingFields);
                    $this->applyChangesToReport($report, $mergedData, $conflict->conflicting_user_id);
                    break;

                case 'custom':
                    // Would require additional custom resolution data
                    break;
            }

            // Update conflict status
            DB::table('conflict_resolution_queue')
                ->where('id', $conflictId)
                ->update([
                    'status' => 'resolved',
                    'resolved_by' => $adminId,
                    'resolution_action' => $resolutionAction,
                    'resolution_notes' => $notes,
                    'resolved_at' => now(),
                    'updated_at' => now()
                ]);

            // Create audit trail
            $this->createAuditTrailRecord($report->id, $adminId, 'web', ['conflict_resolution'], [
                'conflict_resolution' => [
                    'old' => 'pending',
                    'new' => 'resolved',
                    'action' => $resolutionAction,
                    'notes' => $notes
                ]
            ], 'Conflict resolved by admin');

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate conflict severity based on fields involved
     */
    private function calculateConflictSeverity($conflictingFields)
    {
        $criticalFields = ['status', 'severity_level', 'disaster_type', 'location'];
        $moderateFields = ['description', 'priority_level'];

        $criticalCount = count(array_intersect($conflictingFields, $criticalFields));
        $moderateCount = count(array_intersect($conflictingFields, $moderateFields));

        if ($criticalCount > 0) {
            return 'high';
        } elseif ($moderateCount > 0) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Apply changes to report after conflict resolution
     */
    private function applyChangesToReport($report, $changes, $userId)
    {
        foreach ($changes as $field => $value) {
            if (in_array($field, $report->getFillable())) {
                $report->$field = $value;
            }
        }

        $report->version = $report->version + 1;
        $report->last_modified_at = now();
        $report->last_modified_by = $userId;
        $report->last_modified_platform = 'conflict_resolution';

        $report->save();

        // Create audit trail
        $this->createAuditTrailRecord(
            $report->id,
            $userId,
            'conflict_resolution',
            array_keys($changes),
            $changes,
            'Applied changes after conflict resolution'
        );
    }

    /**
     * Create audit trail entry
     */
    private function createAuditTrailRecord($reportId, $userId, $platform, $modifiedFields, $fieldChanges, $reason = null)
    {
        DB::table('disaster_report_audit_trails')->insert([
            'report_id' => $reportId,
            'modified_by' => $userId,
            'user_platform' => $platform,
            'modified_fields' => json_encode($modifiedFields),
            'field_changes' => json_encode($fieldChanges),
            'modification_timestamp' => now(),
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'modification_reason' => $reason,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
