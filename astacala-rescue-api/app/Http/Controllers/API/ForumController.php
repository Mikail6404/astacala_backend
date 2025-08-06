<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DisasterReport;
use App\Models\ForumMessage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ForumController extends Controller
{
    /**
     * Get all messages - General forum listing (when no specific disaster report)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get recent forum messages across all disaster reports
            $messages = ForumMessage::with(['user', 'disasterReport'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages->items(),
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'total_pages' => $messages->lastPage(),
                        'total_messages' => $messages->total(),
                        'has_more' => $messages->hasMorePages(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve forum messages: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all messages for a specific disaster report
     */
    public function indexForReport(Request $request, $disasterReportId): JsonResponse
    {
        try {
            // Verify disaster report exists
            $disasterReport = DisasterReport::findOrFail($disasterReportId);

            // Get messages with pagination
            $messages = ForumMessage::forDisasterReport($disasterReportId)
                ->topLevel()
                ->with(['user', 'replies.user', 'replies.replies'])
                ->orderBy('priority_level', 'desc')
                ->orderBy('created_at', 'asc')
                ->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => $messages,
                'disaster_report' => [
                    'id' => $disasterReport->id,
                    'title' => $disasterReport->title,
                    'type' => $disasterReport->disaster_type,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve forum messages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new message
     */
    public function store(Request $request, $disasterReportId): JsonResponse
    {
        try {
            // Verify disaster report exists
            $disasterReport = DisasterReport::findOrFail($disasterReportId);

            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000',
                'parent_message_id' => 'nullable|exists:forum_messages,id',
                'message_type' => 'in:text,emergency,update,question',
                'priority_level' => 'in:low,normal,high,emergency',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Create the message
            $message = ForumMessage::create([
                'disaster_report_id' => $disasterReportId,
                'user_id' => Auth::id(),
                'parent_message_id' => $request->parent_message_id,
                'message' => $request->message,
                'message_type' => $request->message_type ?? 'text',
                'priority_level' => $request->priority_level ?? 'normal',
            ]);

            // Load relationships for response
            $message->load(['user', 'replies.user']);

            return response()->json([
                'status' => 'success',
                'message' => 'Message posted successfully',
                'data' => $message,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to post message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a message (edit)
     */
    public function update(Request $request, $disasterReportId, $messageId): JsonResponse
    {
        try {
            $message = ForumMessage::where('disaster_report_id', $disasterReportId)
                ->where('id', $messageId)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $message->update([
                'message' => $request->message,
                'edited_at' => now(),
            ]);

            $message->load(['user', 'replies.user']);

            return response()->json([
                'status' => 'success',
                'message' => 'Message updated successfully',
                'data' => $message,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a message
     */
    public function destroy($disasterReportId, $messageId): JsonResponse
    {
        try {
            $message = ForumMessage::where('disaster_report_id', $disasterReportId)
                ->where('id', $messageId)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $message->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Message deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request, $disasterReportId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'message_ids' => 'required|array',
                'message_ids.*' => 'exists:forum_messages,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            ForumMessage::whereIn('id', $request->message_ids)
                ->where('disaster_report_id', $disasterReportId)
                ->update(['is_read' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'Messages marked as read',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get message statistics for a disaster report
     */
    public function statistics($disasterReportId): JsonResponse
    {
        try {
            $stats = [
                'total_messages' => ForumMessage::forDisasterReport($disasterReportId)->count(),
                'unread_messages' => ForumMessage::forDisasterReport($disasterReportId)
                    ->where('is_read', false)
                    ->where('user_id', '!=', Auth::id())
                    ->count(),
                'emergency_messages' => ForumMessage::forDisasterReport($disasterReportId)
                    ->where('priority_level', 'emergency')
                    ->count(),
                'active_users' => ForumMessage::forDisasterReport($disasterReportId)
                    ->distinct('user_id')
                    ->count('user_id'),
                'last_activity' => ForumMessage::forDisasterReport($disasterReportId)
                    ->latest()
                    ->first()?->created_at,
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
