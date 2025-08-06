<?php

namespace App\Services;

use App\Models\DisasterReport;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Cross-Platform Notification Service
 * Handles notifications for both mobile and web platforms
 */
class CrossPlatformNotificationService
{
    /**
     * Send notification to mobile user when report is verified
     */
    public function notifyReportVerified(DisasterReport $report): void
    {
        $user = $report->reporter;
        if (! $user) {
            return;
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'recipient_id' => $user->id, // For backward compatibility
            'title' => 'Laporan Anda Telah Diverifikasi',
            'message' => "Laporan '{$report->title}' telah diverifikasi oleh admin dan sekarang aktif.",
            'type' => 'report_verified',
            'priority' => 'MEDIUM',
            'data' => [
                'report_id' => $report->id,
                'report_title' => $report->title,
                'verification_status' => $report->verification_status,
                'platform' => 'mobile',
            ],
            'is_read' => false,
        ]);

        // Send push notification to mobile
        $this->sendPushNotification($user, $notification);

        Log::info('Report verification notification sent', [
            'user_id' => $user->id,
            'report_id' => $report->id,
            'notification_id' => $notification->id,
        ]);
    }

    /**
     * Send notification to web admin when new report is submitted
     */
    public function notifyNewReportToAdmins(DisasterReport $report): void
    {
        // Get all admin users (for web dashboard)
        $admins = User::where('role', 'ADMIN')->get();

        foreach ($admins as $admin) {
            $notification = Notification::create([
                'user_id' => $admin->id,
                'recipient_id' => $admin->id, // For backward compatibility
                'title' => 'Laporan Bencana Baru',
                'message' => "Laporan baru '{$report->title}' di {$report->location_name} perlu diverifikasi.",
                'type' => 'new_report',
                'priority' => 'HIGH',
                'data' => [
                    'report_id' => $report->id,
                    'report_title' => $report->title,
                    'location' => $report->location_name,
                    'severity' => $report->severity_level,
                    'reporter_name' => $report->reporter->name ?? 'Unknown',
                    'platform' => 'web',
                ],
                'is_read' => false,
            ]);

            // Send web notification (could be WebSocket, email, etc.)
            $this->sendWebNotification($admin, $notification);
        }

        Log::info('New report notifications sent to admins', [
            'report_id' => $report->id,
            'admin_count' => $admins->count(),
        ]);
    }

    /**
     * Send notification to volunteer when admin publishes field update
     */
    public function notifyFieldUpdate($publication): void
    {
        // Get all volunteer users (mobile app users)
        $volunteers = User::where('role', 'VOLUNTEER')->get();

        foreach ($volunteers as $volunteer) {
            $notification = Notification::create([
                'user_id' => $volunteer->id,
                'recipient_id' => $volunteer->id, // For backward compatibility
                'title' => 'Update Lapangan',
                'message' => $publication->content,
                'type' => 'field_update',
                'priority' => 'MEDIUM',
                'data' => [
                    'publication_id' => $publication->id,
                    'publication_title' => $publication->title,
                    'platform' => 'mobile',
                ],
                'is_read' => false,
            ]);

            // Send push notification to mobile
            $this->sendPushNotification($volunteer, $notification);
        }

        Log::info('Field update notifications sent to volunteers', [
            'publication_id' => $publication->id,
            'volunteer_count' => $volunteers->count(),
        ]);
    }

    /**
     * Send push notification to mobile users
     */
    private function sendPushNotification(User $user, Notification $notification): void
    {
        // Integration with Firebase Cloud Messaging or similar
        // For now, we'll log the notification
        Log::info('Push notification queued for mobile user', [
            'user_id' => $user->id,
            'notification_id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
        ]);

        // TODO: Implement actual push notification sending
        // Example:
        // $this->firebaseService->sendToUser($user->fcm_token, $notification);
    }

    /**
     * Send notification to web dashboard (real-time)
     */
    private function sendWebNotification(User $admin, Notification $notification): void
    {
        // Integration with WebSocket or Server-Sent Events
        Log::info('Web notification queued for admin user', [
            'admin_id' => $admin->id,
            'notification_id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
        ]);

        // TODO: Implement real-time web notification
        // Example:
        // broadcast(new AdminNotificationEvent($notification))->toOthers();
    }

    /**
     * Get platform-specific notifications
     */
    public function getPlatformNotifications(User $user, string $platform = 'mobile'): array
    {
        $query = Notification::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('recipient_id', $user->id);
        })
            ->where('data->platform', $platform)
            ->orderBy('created_at', 'desc')
            ->limit(50);

        return $query->get()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'is_read' => $notification->is_read,
                'data' => $notification->data,
                'created_at' => $notification->created_at->toISOString(),
                'formatted_time' => $notification->created_at->diffForHumans(),
            ];
        })->toArray();
    }

    /**
     * Mark notification as read with platform context
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            Log::info('Notification marked as read', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'platform' => $notification->data['platform'] ?? 'unknown',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Get unread notification count by platform
     */
    public function getUnreadCount(User $user, ?string $platform = null): int
    {
        $query = Notification::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('recipient_id', $user->id);
        })->where('is_read', false);

        if ($platform) {
            $query->where('data->platform', $platform);
        }

        return $query->count();
    }

    /**
     * Send urgent notification to all platforms
     */
    public function sendUrgentNotification(string $title, string $message, array $data = []): void
    {
        $allUsers = User::all();

        foreach ($allUsers as $user) {
            $platformData = array_merge($data, [
                'platform' => $user->role === 'ADMIN' ? 'web' : 'mobile',
                'urgent' => true,
            ]);

            $notification = Notification::create([
                'user_id' => $user->id,
                'recipient_id' => $user->id, // For backward compatibility
                'title' => $title,
                'message' => $message,
                'type' => 'urgent_system',
                'priority' => 'HIGH',
                'data' => $platformData,
                'is_read' => false,
            ]);

            // Send to appropriate platform
            if ($user->role === 'ADMIN') {
                $this->sendWebNotification($user, $notification);
            } else {
                $this->sendPushNotification($user, $notification);
            }
        }

        Log::info('Urgent notification sent to all users', [
            'title' => $title,
            'user_count' => $allUsers->count(),
        ]);
    }
}
