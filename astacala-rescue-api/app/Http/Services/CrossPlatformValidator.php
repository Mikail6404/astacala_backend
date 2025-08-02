<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Cross-Platform Validation Service
 * Handles validation rules for both mobile and web platforms
 */
class CrossPlatformValidator
{
    /**
     * Validate mobile disaster report submission
     */
    public function validateMobileReport(array $data): array
    {
        $validator = Validator::make($data, [
            'title' => 'required|string|max:255|min:10',
            'description' => 'required|string|max:2000|min:20',
            'disaster_type' => 'required|string|in:earthquake,flood,fire,hurricane,tsunami,landslide,volcano,drought,blizzard,tornado,other',
            'severity_level' => 'required|string|in:low,medium,high,critical,1,2,3,4',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_name' => 'required|string|max:255|min:3',
            'address' => 'nullable|string|max:500',
            'estimated_affected' => 'nullable|integer|min:0|max:1000000',
            'weather_condition' => 'nullable|string|max:100',
            'incident_timestamp' => 'required|date|before_or_equal:now',

            // Mobile-specific fields
            'app_version' => 'nullable|string|max:20',
            'device_info' => 'nullable|array',
            'device_info.model' => 'nullable|string|max:100',
            'device_info.os' => 'nullable|string|max:50',
            'device_info.os_version' => 'nullable|string|max:20',
            'location_accuracy' => 'nullable|numeric|min:0|max:1000',
            'network_type' => 'nullable|string|in:wifi,cellular,unknown',

            // Images handling
            'images' => 'nullable|array|max:5',
            'images.*' => 'file|mimes:jpeg,jpg,png,webp|max:5120', // 5MB max per image

            // Emergency contact
            'emergency_contact' => 'nullable|string|max:255',
        ], [
            'title.required' => 'Report title is required',
            'title.min' => 'Report title must be at least 10 characters',
            'description.required' => 'Report description is required',
            'description.min' => 'Report description must be at least 20 characters',
            'disaster_type.required' => 'Disaster type is required',
            'disaster_type.in' => 'Invalid disaster type selected',
            'severity_level.required' => 'Severity level is required',
            'severity_level.in' => 'Invalid severity level',
            'latitude.required' => 'Location latitude is required',
            'latitude.between' => 'Invalid latitude value',
            'longitude.required' => 'Location longitude is required',
            'longitude.between' => 'Invalid longitude value',
            'location_name.required' => 'Location name is required',
            'location_name.min' => 'Location name must be at least 3 characters',
            'incident_timestamp.required' => 'Incident time is required',
            'incident_timestamp.before_or_equal' => 'Incident time cannot be in the future',
            'images.max' => 'Maximum 5 images allowed',
            'images.*.mimes' => 'Images must be JPEG, JPG, PNG, or WebP format',
            'images.*.max' => 'Each image must be less than 5MB',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validate web dashboard report submission
     */
    public function validateWebReport(array $data): array
    {
        $validator = Validator::make($data, [
            'title' => 'required|string|max:255|min:10',
            'description' => 'required|string|max:3000|min:20',
            'disaster_type' => 'required|string|in:earthquake,flood,fire,hurricane,tsunami,landslide,volcano,drought,blizzard,tornado,other',
            'severity_level' => 'required|string|in:low,medium,high,critical',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_name' => 'required|string|max:255|min:3',
            'address' => 'nullable|string|max:500',
            'estimated_affected' => 'nullable|integer|min:0|max:10000000',
            'weather_condition' => 'nullable|string|max:100',
            'incident_timestamp' => 'required|date|before_or_equal:now',

            // Web-specific fields
            'team_name' => 'nullable|string|max:255',
            'reporter_contact' => 'nullable|string|max:255',
            'emergency_level' => 'nullable|string|in:low,medium,high,critical',
            'organization' => 'nullable|string|max:200',
            'reference_number' => 'nullable|string|max:50',

            // Images handling (URLs for web)
            'images' => 'nullable|array|max:10',
            'images.*' => 'url|regex:/\.(jpeg|jpg|png|webp)$/i',

            // Additional verification fields
            'source_verification' => 'nullable|string|max:500',
            'cross_reference' => 'nullable|string|max:500',
        ], [
            'title.required' => 'Report title is required',
            'title.min' => 'Report title must be at least 10 characters',
            'description.required' => 'Report description is required',
            'description.min' => 'Report description must be at least 20 characters',
            'disaster_type.required' => 'Disaster type is required',
            'disaster_type.in' => 'Invalid disaster type selected',
            'severity_level.required' => 'Severity level is required',
            'severity_level.in' => 'Invalid severity level',
            'latitude.required' => 'Location latitude is required',
            'latitude.between' => 'Invalid latitude value',
            'longitude.required' => 'Location longitude is required',
            'longitude.between' => 'Invalid longitude value',
            'location_name.required' => 'Location name is required',
            'incident_timestamp.required' => 'Incident time is required',
            'incident_timestamp.before_or_equal' => 'Incident time cannot be in the future',
            'images.max' => 'Maximum 10 images allowed',
            'images.*.url' => 'Invalid image URL format',
            'images.*.regex' => 'Image URLs must point to JPEG, JPG, PNG, or WebP files',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validate publication data (web dashboard)
     */
    public function validatePublication(array $data, bool $isUpdate = false): array
    {
        $rules = [
            'title' => 'required|string|max:255|min:5',
            'content' => 'required|string|min:50',
            'type' => 'required|string|in:article,guide,announcement,report_summary',
            'category' => 'required|string|max:100|min:3',
            'tags' => 'nullable|string|max:500',
            'featured_image' => 'nullable|url',
            'status' => 'required|string|in:draft,published,archived',
            'meta_description' => 'nullable|string|max:160',
            'publish_at' => 'nullable|date|after:now',
            'related_report_ids' => 'nullable|array|max:10',
            'related_report_ids.*' => 'integer|exists:disaster_reports,id',
        ];

        // Make some fields optional for updates
        if ($isUpdate) {
            $rules['title'] = 'sometimes|' . $rules['title'];
            $rules['content'] = 'sometimes|' . $rules['content'];
            $rules['type'] = 'sometimes|' . $rules['type'];
            $rules['category'] = 'sometimes|' . $rules['category'];
            $rules['status'] = 'sometimes|' . $rules['status'];
        }

        $validator = Validator::make($data, $rules, [
            'title.required' => 'Publication title is required',
            'title.min' => 'Title must be at least 5 characters',
            'content.required' => 'Publication content is required',
            'content.min' => 'Content must be at least 50 characters',
            'type.required' => 'Publication type is required',
            'type.in' => 'Invalid publication type',
            'category.required' => 'Category is required',
            'status.required' => 'Publication status is required',
            'status.in' => 'Invalid publication status',
            'featured_image.url' => 'Featured image must be a valid URL',
            'meta_description.max' => 'Meta description cannot exceed 160 characters',
            'publish_at.after' => 'Publish date must be in the future',
            'related_report_ids.max' => 'Maximum 10 related reports allowed',
            'related_report_ids.*.exists' => 'One or more related reports do not exist',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validate user management data
     */
    public function validateUserManagement(array $data, string $action = 'create'): array
    {
        $rules = [];

        switch ($action) {
            case 'create':
                $rules = [
                    'name' => 'required|string|max:255|min:2',
                    'email' => 'required|email|unique:users,email|max:255',
                    'password' => 'required|string|min:8|confirmed',
                    'role' => 'required|string|in:user,admin,super_admin',
                    'organization' => 'nullable|string|max:200',
                    'phone' => 'nullable|string|max:20',
                ];
                break;

            case 'update_role':
                $rules = [
                    'role' => 'required|string|in:user,admin,super_admin',
                    'reason' => 'nullable|string|max:500',
                ];
                break;

            case 'update_status':
                $rules = [
                    'is_active' => 'required|boolean',
                    'reason' => 'nullable|string|max:500',
                ];
                break;

            case 'update_profile':
                $rules = [
                    'name' => 'sometimes|required|string|max:255|min:2',
                    'organization' => 'nullable|string|max:200',
                    'phone' => 'nullable|string|max:20',
                    'emergency_contacts' => 'nullable|array|max:3',
                    'emergency_contacts.*.name' => 'required|string|max:100',
                    'emergency_contacts.*.phone' => 'required|string|max:20',
                    'emergency_contacts.*.relationship' => 'required|string|max:50',
                ];
                break;
        }

        $validator = Validator::make($data, $rules, [
            'name.required' => 'Name is required',
            'name.min' => 'Name must be at least 2 characters',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'Email is already registered',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'role.required' => 'User role is required',
            'role.in' => 'Invalid user role',
            'phone.max' => 'Phone number is too long',
            'organization.max' => 'Organization name is too long',
            'emergency_contacts.max' => 'Maximum 3 emergency contacts allowed',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validate notification data
     */
    public function validateNotification(array $data, string $type = 'individual'): array
    {
        $rules = [
            'title' => 'required|string|max:100|min:5',
            'message' => 'required|string|max:500|min:10',
            'type' => 'required|string|in:info,warning,error,success',
            'priority' => 'required|string|in:low,medium,high,urgent',
        ];

        if ($type === 'broadcast') {
            $rules['target_audience'] = 'required|string|in:all,admins,users,region_specific';
            $rules['region_filter'] = 'nullable|string|max:100';
            $rules['role_filter'] = 'nullable|array';
            $rules['role_filter.*'] = 'string|in:user,admin,super_admin';
            $rules['schedule_at'] = 'nullable|date|after:now';
        } else {
            $rules['recipient_id'] = 'required|integer|exists:users,id';
        }

        $validator = Validator::make($data, $rules, [
            'title.required' => 'Notification title is required',
            'title.min' => 'Title must be at least 5 characters',
            'message.required' => 'Notification message is required',
            'message.min' => 'Message must be at least 10 characters',
            'type.required' => 'Notification type is required',
            'type.in' => 'Invalid notification type',
            'priority.required' => 'Priority level is required',
            'priority.in' => 'Invalid priority level',
            'target_audience.required' => 'Target audience is required for broadcast',
            'recipient_id.required' => 'Recipient is required',
            'recipient_id.exists' => 'Recipient does not exist',
            'schedule_at.after' => 'Schedule time must be in the future',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validate admin actions (verify, publish, etc.)
     */
    public function validateAdminAction(array $data, string $action): array
    {
        $rules = [];

        switch ($action) {
            case 'verify_report':
                $rules = [
                    'verification_notes' => 'nullable|string|max:1000',
                    'severity_adjustment' => 'nullable|string|in:LOW,MEDIUM,HIGH,CRITICAL',
                    'assign_team' => 'nullable|string|max:255',
                    'priority_level' => 'nullable|integer|min:1|max:10',
                ];
                break;

            case 'publish_report':
                $rules = [
                    'public_summary' => 'nullable|string|max:500',
                    'publish_level' => 'required|string|in:public,restricted,internal',
                    'emergency_alert' => 'boolean',
                    'notification_regions' => 'nullable|array',
                    'notification_regions.*' => 'string|max:100',
                ];
                break;

            case 'assign_team':
                $rules = [
                    'team_name' => 'required|string|max:255',
                    'team_contact' => 'nullable|string|max:255',
                    'estimated_response_time' => 'nullable|integer|min:1|max:72', // hours
                    'notes' => 'nullable|string|max:500',
                ];
                break;
        }

        $validator = Validator::make($data, $rules, [
            'verification_notes.max' => 'Verification notes cannot exceed 1000 characters',
            'severity_adjustment.in' => 'Invalid severity level',
            'assign_team.max' => 'Team name is too long',
            'public_summary.max' => 'Public summary cannot exceed 500 characters',
            'publish_level.required' => 'Publish level is required',
            'publish_level.in' => 'Invalid publish level',
            'team_name.required' => 'Team name is required',
            'estimated_response_time.min' => 'Response time must be at least 1 hour',
            'estimated_response_time.max' => 'Response time cannot exceed 72 hours',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validate search and filter parameters
     */
    public function validateSearchFilters(array $data, string $context = 'reports'): array
    {
        $rules = [
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|max:50',
            'sort_order' => 'nullable|string|in:asc,desc',
        ];

        switch ($context) {
            case 'reports':
                $rules['status_filter'] = 'nullable|string';
                $rules['severity_filter'] = 'nullable|string';
                $rules['disaster_type_filter'] = 'nullable|string';
                $rules['date_from'] = 'nullable|date';
                $rules['date_to'] = 'nullable|date|after_or_equal:date_from';
                $rules['location_radius'] = 'nullable|numeric|min:1|max:1000';
                $rules['center_lat'] = 'nullable|numeric|between:-90,90';
                $rules['center_lng'] = 'nullable|numeric|between:-180,180';
                break;

            case 'users':
                $rules['role_filter'] = 'nullable|string|in:user,admin,super_admin';
                $rules['status_filter'] = 'nullable|string|in:active,inactive';
                $rules['organization_filter'] = 'nullable|string|max:200';
                break;

            case 'publications':
                $rules['type_filter'] = 'nullable|string|in:article,guide,announcement,report_summary';
                $rules['category_filter'] = 'nullable|string|max:100';
                $rules['status_filter'] = 'nullable|string|in:draft,published,archived';
                break;
        }

        $validator = Validator::make($data, $rules, [
            'search.max' => 'Search term is too long',
            'page.min' => 'Page number must be at least 1',
            'per_page.min' => 'Items per page must be at least 1',
            'per_page.max' => 'Maximum 100 items per page',
            'sort_order.in' => 'Sort order must be asc or desc',
            'date_to.after_or_equal' => 'End date must be after or equal to start date',
            'location_radius.min' => 'Radius must be at least 1 km',
            'location_radius.max' => 'Radius cannot exceed 1000 km',
            'center_lat.between' => 'Invalid latitude value',
            'center_lng.between' => 'Invalid longitude value',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Sanitize input data
     */
    public function sanitizeInput(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Remove potential XSS
                $data[$key] = strip_tags($value);

                // Trim whitespace
                $data[$key] = trim($data[$key]);

                // Convert special characters
                $data[$key] = htmlspecialchars($data[$key], ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeInput($value);
            }
        }

        return $data;
    }

    /**
     * Validate file uploads
     */
    public function validateFileUpload($file, string $type = 'image'): bool
    {
        if (!$file || !$file->isValid()) {
            return false;
        }

        switch ($type) {
            case 'image':
                $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                break;

            case 'document':
                $allowedMimes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $maxSize = 10 * 1024 * 1024; // 10MB
                break;

            default:
                return false;
        }

        return in_array($file->getMimeType(), $allowedMimes) &&
            $file->getSize() <= $maxSize;
    }
}
