<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

/**
 * Temporary File Upload Controller - Basic Implementation
 * Bypasses image processing to fix GD extension issue
 */
class BasicFileUploadController extends Controller
{
    /**
     * Handle avatar upload without image processing
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'avatar' => 'required|file|max:10240', // Simplified validation - just check it's a file
            ]);

            $file = $request->file('avatar');

            // Generate unique filename
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Store file in public storage
            $path = $file->storeAs('avatars', $filename, 'public');

            // Get full URL
            $url = Storage::url($path);

            // Update user avatar if authenticated
            if ($user = Auth::user()) {
                $user->profile_picture_url = $url;
                $user->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'url' => $url,
                    'path' => $path,
                    'filename' => $filename
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
