<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\CrossPlatformNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    private CrossPlatformNotificationService $notificationService;

    public function __construct(CrossPlatformNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user notifications with cross-platform support.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $platform = $request->get('platform', $user->role === 'ADMIN' ? 'web' : 'mobile');
        $page = $request->get('page', 1);
        $limit = min($request->get('limit', 20), 50);

        try {
            // Use enhanced cross-platform service
            $notifications = $this->notificationService->getPlatformNotifications($user, $platform);
            $unreadCount = $this->notificationService->getUnreadCount($user, $platform);

            // Paginate results
            $offset = ($page - 1) * $limit;
            $paginatedNotifications = array_slice($notifications, $offset, $limit);

            return response()->json([
                'success' => true,
                'data' => $paginatedNotifications,
                'pagination' => [
                    'currentPage' => (int) $page,
                    'totalPages' => ceil(count($notifications) / $limit),
                    'total' => count($notifications),
                    'perPage' => $limit,
                ],
                'unread_count' => $unreadCount,
                'platform' => $platform,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications', [
                'user_id' => $user->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
            ], 500);
        }
    }

    /**
     * Mark notifications as read with cross-platform support.
     */
    public function markAsRead(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'notificationIds' => 'sometimes|array',
            'notificationIds.*' => 'integer|exists:notifications,id',
            'markAll' => 'sometimes|boolean',
            'platform' => 'sometimes|string|in:mobile,web',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $platform = $request->get('platform', $user->role === 'ADMIN' ? 'web' : 'mobile');

            if ($request->has('notificationIds')) {
                $updated = 0;
                foreach ($request->notificationIds as $notificationId) {
                    if ($this->notificationService->markAsRead($notificationId, $user->id)) {
                        $updated++;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => "Marked {$updated} notifications as read",
                ]);
            } elseif ($request->markAll) {
                $updated = Notification::where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere('recipient_id', $user->id);
                })
                    ->where('is_read', false)
                    ->where('data->platform', $platform)
                    ->update([
                        'is_read' => true,
                        'read_at' => now(),
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => "Marked {$updated} notifications as read",
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Either notificationIds or markAll must be provided',
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('Error marking notifications as read', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as read',
            ], 500);
        }
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id)
    {
        $notification = Notification::where('id', $id)
            ->where('recipient_id', $request->user()->id)
            ->first();

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully',
        ]);
    }

    /**
     * Create a new notification (for admin/system use).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipient_id' => 'required|uuid|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:EMERGENCY,UPDATE,SYSTEM,COMMUNITY',
            'priority' => 'required|in:HIGH,MEDIUM,LOW',
            'related_report_id' => 'nullable|uuid|exists:disaster_reports,id',
            'action_url' => 'nullable|string|max:500',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $notification = Notification::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully',
            'data' => $notification,
        ], 201);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $platform = $request->get('platform', $user->role === 'ADMIN' ? 'web' : 'mobile');

        try {
            $unreadCount = $this->notificationService->getUnreadCount($user, $platform);

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $unreadCount,
                    'platform' => $platform,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting unread count', [
                'user_id' => $user->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
            ], 500);
        }
    }

    /**
     * Send urgent system notification (Admin only)
     */
    public function sendUrgentNotification(Request $request)
    {
        $user = Auth::user();
        if (! $user || $user->role !== 'ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->notificationService->sendUrgentNotification(
                $request->title,
                $request->message,
                $request->get('data', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Urgent notification sent to all users',
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending urgent notification', [
                'admin_id' => $user->id,
                'title' => $request->title,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send urgent notification',
            ], 500);
        }
    }

    /**
     * Update FCM token for push notifications (Mobile only)
     */
    public function updateFcmToken(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user->fcm_token = $request->fcm_token;
            $user->save();

            Log::info('FCM token updated', [
                'user_id' => $user->id,
                'token_length' => strlen($request->fcm_token),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating FCM token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update FCM token',
            ], 500);
        }
    }
}
