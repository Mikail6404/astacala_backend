<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * User Context Service
 * 
 * Simple user context without role changes
 * Platform determines capabilities, not user roles
 */
class UserContextService
{
    /**
     * Check if the request is from a mobile user
     */
    public function isMobileUser(Request $request): bool
    {
        return $request->get('authenticated_as') === 'mobile_user';
    }

    /**
     * Check if the request is from a web admin
     */
    public function isWebAdmin(Request $request): bool
    {
        return $request->get('authenticated_as') === 'web_admin';
    }

    /**
     * Get platform type from request
     */
    public function getPlatform(Request $request): string
    {
        return $request->get('platform', 'unknown');
    }

    /**
     * Get user type from request
     */
    public function getUserType(Request $request): string
    {
        return $request->get('user_type', 'unknown');
    }

    /**
     * Get authentication method used
     */
    public function getAuthMethod(Request $request): string
    {
        return $request->get('auth_method', 'unknown');
    }

    /**
     * Get comprehensive user context without role-based access control
     */
    public function getUserContext(Request $request): array
    {
        if ($this->isMobileUser($request)) {
            $user = Auth::guard('sanctum')->user();

            return [
                'platform' => 'mobile',
                'user_type' => 'volunteer',
                'auth_method' => 'jwt_token',
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? null
                ] : null,
                'permissions' => $this->getMobilePermissions(),
                'context_valid' => true
            ];
        }

        if ($this->isWebAdmin($request)) {
            return [
                'platform' => 'web',
                'user_type' => 'admin',
                'auth_method' => 'session_cookie',
                'admin_id' => $request->get('admin_id'),
                'permissions' => $this->getWebAdminPermissions(),
                'context_valid' => true
            ];
        }

        return [
            'platform' => 'unknown',
            'user_type' => 'unknown',
            'auth_method' => 'none',
            'context_valid' => false,
            'error' => 'No valid authentication context found'
        ];
    }

    /**
     * Get mobile platform permissions
     */
    public function getMobilePermissions(): array
    {
        return [
            'submit_report',
            'view_own_reports',
            'update_own_profile',
            'view_field_updates',
            'access_forum',
            'receive_notifications',
            'upload_images'
        ];
    }

    /**
     * Get web admin platform permissions  
     */
    public function getWebAdminPermissions(): array
    {
        return [
            'verify_reports',
            'manage_volunteers',
            'create_field_updates',
            'view_statistics',
            'access_admin_dashboard',
            'bulk_operations',
            'manage_publications',
            'send_notifications',
            'view_all_reports',
            'admin_user_management'
        ];
    }

    /**
     * Check if user has specific permission based on platform
     */
    public function hasPermission(Request $request, string $permission): bool
    {
        $context = $this->getUserContext($request);

        if (!$context['context_valid']) {
            return false;
        }

        return in_array($permission, $context['permissions']);
    }

    /**
     * Validate authentication context
     */
    public function validateContext(Request $request): array
    {
        $context = $this->getUserContext($request);

        $validation = [
            'is_valid' => $context['context_valid'],
            'platform' => $context['platform'],
            'user_type' => $context['user_type'],
            'auth_method' => $context['auth_method'],
            'timestamp' => now()->toISOString()
        ];

        if (!$context['context_valid']) {
            $validation['errors'] = [
                'context_invalid',
                $context['error'] ?? 'Unknown authentication error'
            ];
        }

        Log::info('UserContextService: Context validation', $validation);

        return $validation;
    }

    /**
     * Log authentication context for debugging
     */
    public function logContext(Request $request, string $action = 'context_check'): void
    {
        $context = $this->getUserContext($request);

        Log::info("UserContextService: {$action}", [
            'url' => $request->url(),
            'method' => $request->method(),
            'platform' => $context['platform'],
            'user_type' => $context['user_type'],
            'auth_method' => $context['auth_method'],
            'context_valid' => $context['context_valid'],
            'user_id' => $context['user']['id'] ?? $context['admin_id'] ?? null,
            'timestamp' => now()->toISOString()
        ]);
    }
}
