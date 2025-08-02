<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\UserContextService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * User Context Service Unit Tests
 * 
 * Tests for platform identification and permission management
 */
class UserContextServiceTest extends TestCase
{
    use RefreshDatabase;

    private $userContextService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userContextService = new UserContextService();
    }

    /**
     * Test mobile user identification
     */
    public function test_mobile_user_identification()
    {
        $request = $this->createMockRequest([
            'authenticated_as' => 'mobile_user',
            'platform' => 'mobile',
            'user_type' => 'volunteer',
            'auth_method' => 'jwt_token'
        ]);

        $this->assertTrue($this->userContextService->isMobileUser($request));
        $this->assertFalse($this->userContextService->isWebAdmin($request));
        $this->assertEquals('mobile', $this->userContextService->getPlatform($request));
        $this->assertEquals('volunteer', $this->userContextService->getUserType($request));
        $this->assertEquals('jwt_token', $this->userContextService->getAuthMethod($request));
    }

    /**
     * Test web admin identification
     */
    public function test_web_admin_identification()
    {
        $request = $this->createMockRequest([
            'authenticated_as' => 'web_admin',
            'platform' => 'web',
            'user_type' => 'admin',
            'auth_method' => 'session_cookie',
            'admin_id' => 1
        ]);

        $this->assertTrue($this->userContextService->isWebAdmin($request));
        $this->assertFalse($this->userContextService->isMobileUser($request));
        $this->assertEquals('web', $this->userContextService->getPlatform($request));
        $this->assertEquals('admin', $this->userContextService->getUserType($request));
        $this->assertEquals('session_cookie', $this->userContextService->getAuthMethod($request));
    }

    /**
     * Test mobile user context
     */
    public function test_mobile_user_context()
    {
        $request = $this->createMockRequest([
            'authenticated_as' => 'mobile_user',
            'platform' => 'mobile',
            'user_type' => 'volunteer',
            'auth_method' => 'jwt_token'
        ]);

        $context = $this->userContextService->getUserContext($request);

        $this->assertEquals('mobile', $context['platform']);
        $this->assertEquals('volunteer', $context['user_type']);
        $this->assertEquals('jwt_token', $context['auth_method']);
        $this->assertTrue($context['context_valid']);
        $this->assertArrayHasKey('permissions', $context);

        $expectedPermissions = [
            'submit_report',
            'view_own_reports',
            'update_own_profile',
            'view_field_updates',
            'access_forum',
            'receive_notifications',
            'upload_images'
        ];

        $this->assertEquals($expectedPermissions, $context['permissions']);
    }

    /**
     * Test web admin context
     */
    public function test_web_admin_context()
    {
        $request = $this->createMockRequest([
            'authenticated_as' => 'web_admin',
            'platform' => 'web',
            'user_type' => 'admin',
            'auth_method' => 'session_cookie',
            'admin_id' => 1
        ]);

        $context = $this->userContextService->getUserContext($request);

        $this->assertEquals('web', $context['platform']);
        $this->assertEquals('admin', $context['user_type']);
        $this->assertEquals('session_cookie', $context['auth_method']);
        $this->assertEquals(1, $context['admin_id']);
        $this->assertTrue($context['context_valid']);

        $expectedPermissions = [
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

        $this->assertEquals($expectedPermissions, $context['permissions']);
    }

    /**
     * Test unknown/invalid context
     */
    public function test_unknown_invalid_context()
    {
        $request = $this->createMockRequest([]);

        $context = $this->userContextService->getUserContext($request);

        $this->assertEquals('unknown', $context['platform']);
        $this->assertEquals('unknown', $context['user_type']);
        $this->assertEquals('none', $context['auth_method']);
        $this->assertFalse($context['context_valid']);
        $this->assertArrayHasKey('error', $context);
    }

    /**
     * Test mobile permissions
     */
    public function test_mobile_permissions()
    {
        $permissions = $this->userContextService->getMobilePermissions();

        $expectedPermissions = [
            'submit_report',
            'view_own_reports',
            'update_own_profile',
            'view_field_updates',
            'access_forum',
            'receive_notifications',
            'upload_images'
        ];

        $this->assertEquals($expectedPermissions, $permissions);
        $this->assertCount(7, $permissions);
    }

    /**
     * Test web admin permissions
     */
    public function test_web_admin_permissions()
    {
        $permissions = $this->userContextService->getWebAdminPermissions();

        $expectedPermissions = [
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

        $this->assertEquals($expectedPermissions, $permissions);
        $this->assertCount(10, $permissions);
    }

    /**
     * Test permission checking for mobile user
     */
    public function test_permission_checking_mobile_user()
    {
        $request = $this->createMockRequest([
            'authenticated_as' => 'mobile_user',
            'platform' => 'mobile',
            'user_type' => 'volunteer',
            'auth_method' => 'jwt_token'
        ]);

        // Test valid mobile permissions
        $this->assertTrue($this->userContextService->hasPermission($request, 'submit_report'));
        $this->assertTrue($this->userContextService->hasPermission($request, 'view_own_reports'));
        $this->assertTrue($this->userContextService->hasPermission($request, 'upload_images'));

        // Test invalid permissions (admin-only)
        $this->assertFalse($this->userContextService->hasPermission($request, 'verify_reports'));
        $this->assertFalse($this->userContextService->hasPermission($request, 'manage_volunteers'));
        $this->assertFalse($this->userContextService->hasPermission($request, 'admin_user_management'));
    }

    /**
     * Test permission checking for web admin
     */
    public function test_permission_checking_web_admin()
    {
        $request = $this->createMockRequest([
            'authenticated_as' => 'web_admin',
            'platform' => 'web',
            'user_type' => 'admin',
            'auth_method' => 'session_cookie',
            'admin_id' => 1
        ]);

        // Test valid admin permissions
        $this->assertTrue($this->userContextService->hasPermission($request, 'verify_reports'));
        $this->assertTrue($this->userContextService->hasPermission($request, 'manage_volunteers'));
        $this->assertTrue($this->userContextService->hasPermission($request, 'view_statistics'));
        $this->assertTrue($this->userContextService->hasPermission($request, 'admin_user_management'));

        // Test permissions that admins don't typically have (mobile-specific)
        $this->assertFalse($this->userContextService->hasPermission($request, 'submit_report'));
        $this->assertFalse($this->userContextService->hasPermission($request, 'access_forum'));
    }

    /**
     * Test context validation
     */
    public function test_context_validation()
    {
        // Test valid mobile context
        $validRequest = $this->createMockRequest([
            'authenticated_as' => 'mobile_user',
            'platform' => 'mobile',
            'user_type' => 'volunteer',
            'auth_method' => 'jwt_token'
        ]);

        $validation = $this->userContextService->validateContext($validRequest);

        $this->assertTrue($validation['is_valid']);
        $this->assertEquals('mobile', $validation['platform']);
        $this->assertEquals('volunteer', $validation['user_type']);
        $this->assertEquals('jwt_token', $validation['auth_method']);
        $this->assertArrayHasKey('timestamp', $validation);
        $this->assertArrayNotHasKey('errors', $validation);

        // Test invalid context
        $invalidRequest = $this->createMockRequest([]);

        $invalidValidation = $this->userContextService->validateContext($invalidRequest);

        $this->assertFalse($invalidValidation['is_valid']);
        $this->assertEquals('unknown', $invalidValidation['platform']);
        $this->assertArrayHasKey('errors', $invalidValidation);
    }

    /**
     * Test permission separation (no cross-platform permissions)
     */
    public function test_permission_separation()
    {
        $mobilePermissions = $this->userContextService->getMobilePermissions();
        $webPermissions = $this->userContextService->getWebAdminPermissions();

        // Ensure no overlap between mobile and web permissions
        $overlap = array_intersect($mobilePermissions, $webPermissions);

        $this->assertEmpty($overlap, 'Mobile and web permissions should not overlap');

        // Ensure each platform has unique capabilities
        $this->assertContains('submit_report', $mobilePermissions);
        $this->assertNotContains('submit_report', $webPermissions);

        $this->assertContains('verify_reports', $webPermissions);
        $this->assertNotContains('verify_reports', $mobilePermissions);
    }

    /**
     * Helper method to create mock request
     */
    private function createMockRequest(array $attributes = [])
    {
        $request = new Request();

        foreach ($attributes as $key => $value) {
            $request->merge([$key => $value]);
        }

        return $request;
    }
}
