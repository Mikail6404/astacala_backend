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
                'birth_date' => $user->birth_date,
                'place_of_birth' => $user->place_of_birth,
                'member_number' => $user->member_number,
                'profilePictureUrl' => $user->profile_picture_url,
                'role' => $user->role,
                'organization' => $user->organization,
                'emergencyContacts' => $user->emergency_contacts ?? [],
                'joinedAt' => $user->created_at,
                'isActive' => $user->is_active,
                'lastLogin' => $user->last_login,
            ],
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
            'birth_date' => 'sometimes|nullable|date',
            'place_of_birth' => 'sometimes|nullable|string|max:255',
            'member_number' => 'sometimes|nullable|string|max:100',
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $user->update($request->only(['name', 'phone', 'address', 'birth_date', 'place_of_birth', 'member_number', 'organization', 'emergency_contacts']));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'birth_date' => $user->birth_date,
                'place_of_birth' => $user->place_of_birth,
                'member_number' => $user->member_number,
                'profilePictureUrl' => $user->profile_picture_url,
                'role' => $user->role,
                'organization' => $user->organization,
                'emergencyContacts' => $user->emergency_contacts ?? [],
                'isActive' => $user->is_active,
            ],
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
                'errors' => $validator->errors(),
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
            ],
        ]);
    }

    /**
     * Get specific user (for admin/coordinator).
     */
    public function getUserById(string $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
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
            ],
        ]);
    }

    /**
     * Get admin list (admin only).
     */
    public function adminList(Request $request)
    {
        try {
            $users = User::whereIn('role', ['ADMIN', 'admin', 'super_admin', 'SUPER_ADMIN'])
                ->select(
                    'id',
                    'name',
                    'email',
                    'role',
                    'is_active',
                    'created_at',
                    'last_login',
                    'birth_date',
                    'place_of_birth',
                    'phone',
                    'organization',
                    'member_number'
                )
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Admin users retrieved successfully',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AdminList Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving admin users: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get volunteer list (admin only).
     */
    public function volunteerList(Request $request)
    {
        try {
            $users = User::whereIn('role', ['VOLUNTEER', 'volunteer', 'coordinator', 'COORDINATOR'])
                ->select('id', 'name', 'email', 'phone', 'role', 'birth_date', 'place_of_birth', 'organization', 'member_number', 'is_active', 'created_at', 'last_login')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Volunteer users retrieved successfully',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('VolunteerList Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving volunteer users: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get user statistics (admin only).
     */
    public function statistics(Request $request)
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $adminUsers = User::whereIn('role', ['ADMIN', 'admin', 'super_admin', 'SUPER_ADMIN'])->count();
        $volunteerUsers = User::whereIn('role', ['VOLUNTEER', 'volunteer', 'USER', 'user'])->count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'User statistics retrieved successfully',
            'data' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'admin_users' => $adminUsers,
                'volunteer_users' => $volunteerUsers,
                'new_users_this_month' => $newUsersThisMonth,
                'inactive_users' => $totalUsers - $activeUsers,
            ],
        ]);
    }

    /**
     * Create admin user (super admin only).
     */
    public function createAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:50',
            'organization' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'phone' => $request->phone,
            'role' => 'ADMIN',
            'organization' => $request->organization,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin user created successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    /**
     * Update user role (admin only).
     */
    public function updateRole(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:USER,VOLUNTEER,ADMIN,SUPER_ADMIN',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->update(['role' => $request->role]);

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Update user status (admin only).
     */
    public function updateStatus(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->update(['is_active' => $request->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    /**
     * Update user profile by ID (admin only).
     */
    public function updateUserById(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'address' => 'sometimes|nullable|string',
            'organization' => 'sometimes|nullable|string|max:255',
            'birth_date' => 'sometimes|nullable|date',
            'place_of_birth' => 'sometimes|nullable|string|max:255',
            'member_number' => 'sometimes|nullable|string|max:100',
            'emergency_contacts' => 'sometimes|array',
            'emergency_contacts.*.name' => 'required_with:emergency_contacts|string|max:255',
            'emergency_contacts.*.phone' => 'required_with:emergency_contacts|string|max:50',
            'emergency_contacts.*.relationship' => 'required_with:emergency_contacts|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->update($request->only([
            'name',
            'phone',
            'address',
            'organization',
            'birth_date',
            'place_of_birth',
            'member_number',
            'emergency_contacts',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'User profile updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'organization' => $user->organization,
                'birth_date' => $user->birth_date,
                'place_of_birth' => $user->place_of_birth,
                'member_number' => $user->member_number,
                'role' => $user->role,
                'emergency_contacts' => $user->emergency_contacts ?? [],
                'is_active' => $user->is_active,
            ],
        ]);
    }

    /**
     * Hard delete user by ID (admin only)
     * For TICKET #005: Admin delete functionality with complete removal
     */
    public function deleteUserById(Request $request, string $id)
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Store user data for response before deletion
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ];

            // Hard delete the user
            $user->delete();

            \Illuminate\Support\Facades\Log::info('User hard deleted', [
                'admin_id' => $request->user()->id,
                'deleted_user_id' => $id,
                'deleted_user_data' => $userData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
                'data' => $userData,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('User deletion failed', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting user: '.$e->getMessage(),
            ], 500);
        }
    }
}
