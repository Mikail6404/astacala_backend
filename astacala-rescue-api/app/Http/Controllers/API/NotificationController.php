<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get user notifications.
     */
    public function index(Request $request)
    {
        $notifications = Notification::where('recipient_id', $request->user()->id)
            ->with('relatedReport')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'pagination' => [
                'currentPage' => $notifications->currentPage(),
                'totalPages' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ]
        ]);
    }

    /**
     * Mark notifications as read.
     */
    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notificationIds' => 'sometimes|array',
            'notificationIds.*' => 'uuid|exists:notifications,id',
            'markAll' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Notification::where('recipient_id', $request->user()->id);

        if ($request->has('notificationIds')) {
            $query->whereIn('id', $request->notificationIds);
        } elseif ($request->markAll) {
            // Mark all notifications as read
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either notificationIds or markAll must be provided'
            ], 422);
        }

        $updated = $query->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$updated} notifications as read"
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id)
    {
        $notification = Notification::where('id', $id)
            ->where('recipient_id', $request->user()->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
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
                'errors' => $validator->errors()
            ], 422);
        }

        $notification = Notification::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully',
            'data' => $notification
        ], 201);
    }
}
