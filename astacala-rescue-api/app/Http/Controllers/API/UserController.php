<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get user profile.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'profilePictureUrl' => $user->profile_picture_url,
                'role' => $user->role,
                'organization' => $user->organization,
                'emergencyContacts' => $user->emergency_contacts ?? [],
                'joinedAt' => $user->created_at,
                'isActive' => $user->is_active,
                'lastLogin' => $user->last_login,
            ]
        ]);
    }

    /**
     * Update user profile.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'address' => 'sometimes|nullable|string',
            'organization' => 'sometimes|nullable|string|max:255',
            'emergencyContacts' => 'sometimes|array',
            'emergencyContacts.*.name' => 'required_with:emergencyContacts|string|max:255',
            'emergencyContacts.*.phone' => 'required_with:emergencyContacts|string|max:50',
            'emergencyContacts.*.relationship' => 'required_with:emergencyContacts|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $user->update($request->only(['name', 'phone', 'address', 'organization', 'emergency_contacts']));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'profilePictureUrl' => $user->profile_picture_url,
                'role' => $user->role,
                'organization' => $user->organization,
                'emergencyContacts' => $user->emergency_contacts ?? [],
                'isActive' => $user->is_active,
            ]
        ]);
    }

    /**
     * Upload profile picture.
     */
    public function uploadAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Delete old profile picture if exists
        if ($user->profile_picture_url) {
            $oldPath = str_replace('/storage/', '', $user->profile_picture_url);
            Storage::disk('public')->delete($oldPath);
        }

        // Store new profile picture
        $path = $request->file('avatar')->store('profile-pictures', 'public');
        $url = Storage::url($path);

        $user->update(['profile_picture_url' => $url]);

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'data' => [
                'profilePictureUrl' => $url,
            ]
        ]);
    }

    /**
     * Get specific user (for admin/coordinator).
     */
    public function getUserById(string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'isActive' => $user->is_active,
                'joinedAt' => $user->created_at,
                'lastLogin' => $user->last_login,
            ]
        ]);
    }
}
