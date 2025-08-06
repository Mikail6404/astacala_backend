<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Services\CrossPlatformDataMapper;
use App\Http\Services\CrossPlatformValidator;
use App\Models\DisasterReport;
use App\Models\ReportImage;
use App\Services\CrossPlatformNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DisasterReportController extends Controller
{
    protected $dataMapper;

    protected $crossValidator;

    protected $notificationService;

    public function __construct(
        CrossPlatformDataMapper $dataMapper,
        CrossPlatformValidator $crossValidator,
        CrossPlatformNotificationService $notificationService
    ) {
        $this->dataMapper = $dataMapper;
        $this->crossValidator = $crossValidator;
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of disaster reports.
     */
    public function index(Request $request)
    {
        $query = DisasterReport::with(['reporter', 'images']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('severity')) {
            $query->where('severity_level', $request->severity);
        }

        if ($request->has('disaster_type')) {
            $query->where('disaster_type', $request->disaster_type);
        }

        // Pagination
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);

        $reports = $query->orderBy('incident_timestamp', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'reports' => $reports->items(),
                'pagination' => [
                    'currentPage' => $reports->currentPage(),
                    'totalPages' => $reports->lastPage(),
                    'totalReports' => $reports->total(),
                    'hasNextPage' => $reports->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Store a newly created disaster report.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'disasterType' => 'required|string|in:EARTHQUAKE,FLOOD,FIRE,LANDSLIDE,TSUNAMI,VOLCANIC',
            'severityLevel' => 'required|string|in:LOW,MEDIUM,HIGH,CRITICAL',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'locationName' => 'nullable|string',
            'estimatedAffected' => 'nullable|integer|min:0',
            'teamName' => 'nullable|string',
            'weatherCondition' => 'nullable|string',
            'incidentTimestamp' => 'required|date',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create the disaster report
        $report = DisasterReport::create([
            'title' => $request->title,
            'description' => $request->description,
            'disaster_type' => $request->disasterType,
            'severity_level' => $request->severityLevel,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'location_name' => $request->locationName,
            'estimated_affected' => $request->estimatedAffected ?? 0,
            'team_name' => $request->teamName,
            'weather_condition' => $request->weatherCondition,
            'incident_timestamp' => $request->incidentTimestamp,
            'reported_by' => $request->user()->id,
            'metadata' => [
                'additionalNotes' => $request->additionalNotes,
                'requiredResources' => $request->requiredResources,
                'contactInfo' => $request->contactInfo,
                'isEmergency' => $request->isEmergency ?? false,
            ],
        ]);

        // Handle image uploads
        $imageUrls = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('disaster-reports', 'public');
                $imageUrl = Storage::url($path);

                ReportImage::create([
                    'report_id' => $report->id,
                    'image_url' => $imageUrl,
                    'upload_order' => $index,
                    'file_size' => $image->getSize(),
                ]);

                $imageUrls[] = $imageUrl;
            }
        }

        // Send notification to admins about new report
        $this->notificationService->notifyNewReportToAdmins($report);

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully',
            'data' => [
                'reportId' => $report->id,
                'status' => $report->status,
                'submittedAt' => $report->created_at,
                'imageUrls' => $imageUrls,
            ],
        ], 201);
    }

    /**
     * Display the specified disaster report.
     */
    public function show(string $id)
    {
        $report = DisasterReport::with(['reporter', 'assignee', 'images'])
            ->find($id);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Update the specified disaster report.
     */
    public function update(Request $request, string $id)
    {
        $report = DisasterReport::find($id);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        // Only allow updates to status and assignment
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:PENDING,ACTIVE,RESOLVED,REJECTED',
            'assigned_to' => 'sometimes|uuid|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldStatus = $report->status;
        $report->update($request->only(['status', 'assigned_to']));

        // Send notification when status changes to verified
        if ($request->has('status') && $request->status === 'VERIFIED' && $oldStatus !== 'VERIFIED') {
            $this->notificationService->notifyReportVerified($report);
        }

        return response()->json([
            'success' => true,
            'message' => 'Report updated successfully',
            'data' => $report,
        ]);
    }

    /**
     * Get dashboard statistics.
     */
    public function statistics()
    {
        $activeReports = DisasterReport::where('status', 'ACTIVE')->count();
        $totalVolunteers = \App\Models\User::where('role', 'VOLUNTEER')->where('is_active', true)->count();

        $recentActivity = DisasterReport::with('reporter')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($report) {
                return [
                    'type' => 'report_submitted',
                    'title' => $report->title,
                    'timestamp' => $report->created_at,
                    'severity' => $report->severity_level,
                ];
            });

        $severityBreakdown = [
            'critical' => DisasterReport::where('severity_level', 'CRITICAL')->where('status', '!=', 'RESOLVED')->count(),
            'high' => DisasterReport::where('severity_level', 'HIGH')->where('status', '!=', 'RESOLVED')->count(),
            'medium' => DisasterReport::where('severity_level', 'MEDIUM')->where('status', '!=', 'RESOLVED')->count(),
            'low' => DisasterReport::where('severity_level', 'LOW')->where('status', '!=', 'RESOLVED')->count(),
        ];

        $disasterTypeBreakdown = [
            'flood' => DisasterReport::where('disaster_type', 'FLOOD')->where('status', '!=', 'RESOLVED')->count(),
            'earthquake' => DisasterReport::where('disaster_type', 'EARTHQUAKE')->where('status', '!=', 'RESOLVED')->count(),
            'fire' => DisasterReport::where('disaster_type', 'FIRE')->where('status', '!=', 'RESOLVED')->count(),
            'landslide' => DisasterReport::where('disaster_type', 'LANDSLIDE')->where('status', '!=', 'RESOLVED')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'activeReports' => $activeReports,
                'readyTeams' => 8, // Mock data for now
                'totalVolunteers' => $totalVolunteers,
                'recentActivity' => $recentActivity,
                'severityBreakdown' => $severityBreakdown,
                'disasterTypeBreakdown' => $disasterTypeBreakdown,
            ],
        ]);
    }

    /**
     * Get disaster reports for the authenticated user (History/Riwayat)
     */
    public function userReports(Request $request)
    {
        $user = $request->user();

        $query = DisasterReport::with(['images'])
            ->where('reported_by', $user->id);

        // Apply filters if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('disaster_type')) {
            $query->where('disaster_type', $request->disaster_type);
        }

        if ($request->has('from_date')) {
            $query->whereDate('incident_timestamp', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('incident_timestamp', '<=', $request->to_date);
        }

        // Pagination
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);

        $reports = $query->orderBy('incident_timestamp', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Get user statistics
        $userStats = [
            'total_reports' => $user->disasterReports()->count(),
            'pending_reports' => $user->disasterReports()->where('status', 'PENDING')->count(),
            'resolved_reports' => $user->disasterReports()->where('status', 'RESOLVED')->count(),
            'in_progress_reports' => $user->disasterReports()->where('status', 'IN_PROGRESS')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'reports' => $reports->items(),
                'pagination' => [
                    'currentPage' => $reports->currentPage(),
                    'totalPages' => $reports->lastPage(),
                    'totalReports' => $reports->total(),
                    'hasNextPage' => $reports->hasMorePages(),
                    'hasPreviousPage' => $reports->currentPage() > 1,
                ],
                'statistics' => $userStats,
            ],
        ]);
    }

    /**
     * Submit a disaster report from web application
     * Cross-platform compatibility endpoint with enhanced validation
     */
    public function webSubmit(Request $request)
    {
        try {
            // Sanitize input data
            $sanitizedData = $this->crossValidator->sanitizeInput($request->all());

            // Validate web report submission
            $validatedData = $this->crossValidator->validateWebReport($sanitizedData);

            // Map web data to unified format
            $unifiedData = $this->dataMapper->mapWebReportToUnified($validatedData);

            // Create the report
            $report = DisasterReport::create($unifiedData);

            // Handle image URLs for web submissions
            if (isset($validatedData['images']) && is_array($validatedData['images'])) {
                foreach ($validatedData['images'] as $imageUrl) {
                    ReportImage::create([
                        'disaster_report_id' => $report->id,
                        'image_path' => $imageUrl,
                        'is_primary' => false,
                        'uploaded_by' => auth()->id(),
                    ]);
                }
            }

            // Load relationships and format response
            $report->load(['reporter', 'images']);
            $responseData = $this->dataMapper->mapUnifiedToWebResponse($report);

            return response()->json([
                'status' => 'success',
                'message' => 'Disaster report submitted successfully from web dashboard',
                'data' => $responseData,
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit disaster report',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get disaster reports formatted for admin web dashboard
     * Enhanced with cross-platform data mapping
     */
    public function adminView(Request $request)
    {
        try {
            // Validate search filters
            $filters = $this->crossValidator->validateSearchFilters($request->all(), 'reports');

            $query = DisasterReport::with(['reporter:id,name,email,phone', 'images'])
                ->select('*');

            // Apply validated filters
            if (isset($filters['status_filter'])) {
                $statuses = explode(',', $filters['status_filter']);
                $query->whereIn('status', $statuses);
            }

            if (isset($filters['severity_filter'])) {
                $severities = explode(',', $filters['severity_filter']);
                $query->whereIn('severity_level', $severities);
            }

            if (isset($filters['disaster_type_filter'])) {
                $types = explode(',', $filters['disaster_type_filter']);
                $query->whereIn('disaster_type', $types);
            }

            if (isset($filters['date_from'])) {
                $query->whereDate('incident_timestamp', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->whereDate('incident_timestamp', '<=', $filters['date_to']);
            }

            if (isset($filters['search'])) {
                $searchTerm = $filters['search'];
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('location_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('disaster_type', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Location radius filtering
            if (isset($filters['location_radius']) && isset($filters['center_lat']) && isset($filters['center_lng'])) {
                $radius = $filters['location_radius'];
                $centerLat = $filters['center_lat'];
                $centerLng = $filters['center_lng'];

                $query->whereRaw('
                    (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                    sin(radians(latitude)))) <= ?
                ', [$centerLat, $centerLng, $centerLat, $radius]);
            }

            // Sorting
            $sortBy = $filters['sort_by'] ?? 'incident_timestamp';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $filters['per_page'] ?? 20;
            $reports = $query->paginate($perPage);

            // Map reports to web dashboard format
            $mappedReports = $reports->getCollection()->map(function ($report) {
                return $this->dataMapper->mapUnifiedToWebResponse($report);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Admin dashboard data retrieved successfully',
                'data' => [
                    'reports' => $mappedReports,
                    'pagination' => [
                        'current_page' => $reports->currentPage(),
                        'total_pages' => $reports->lastPage(),
                        'total_reports' => $reports->total(),
                        'per_page' => $reports->perPage(),
                        'has_next_page' => $reports->hasMorePages(),
                        'has_previous_page' => $reports->currentPage() > 1,
                    ],
                    'summary' => $this->getAdminSummary(),
                    'filters_applied' => $filters,
                ],
            ], Response::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid filter parameters',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve admin dashboard data',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get pending disaster reports for admin review
     */
    public function pending(Request $request)
    {
        $query = DisasterReport::with(['reporter:id,name,email', 'images'])
            ->where('status', 'PENDING')
            ->orderBy('incident_timestamp', 'desc');

        // Priority sorting: Critical and High severity first
        $query->orderByRaw("CASE 
            WHEN severity_level = 'CRITICAL' THEN 1 
            WHEN severity_level = 'HIGH' THEN 2 
            WHEN severity_level = 'MEDIUM' THEN 3 
            WHEN severity_level = 'LOW' THEN 4 
            ELSE 5 END");

        $perPage = $request->get('per_page', 15);
        $reports = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Pending reports retrieved successfully',
            'data' => [
                'pending_reports' => $reports->items(),
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'total_pages' => $reports->lastPage(),
                    'total_pending' => $reports->total(),
                    'per_page' => $reports->perPage(),
                ],
            ],
        ]);
    }

    /**
     * Verify a disaster report (admin action)
     * Enhanced with cross-platform validation
     */
    public function verify(Request $request, $id)
    {
        try {
            $report = DisasterReport::findOrFail($id);

            // Validate admin action data
            $validatedData = $this->crossValidator->validateAdminAction($request->all(), 'verify_report');

            // Update report status and verification info
            $report->status = 'VERIFIED';

            if (isset($validatedData['severity_adjustment'])) {
                $report->severity_level = $validatedData['severity_adjustment'];
            }

            if (isset($validatedData['assign_team'])) {
                $report->assigned_to = $validatedData['assign_team'];
            }

            // Add verification metadata
            $metadata = $report->metadata ?? [];
            $metadata['verification'] = [
                'verified_by' => auth()->id(),
                'verified_at' => now()->toISOString(),
                'verification_notes' => $validatedData['verification_notes'] ?? null,
                'original_severity' => $report->getOriginal('severity_level'),
                'adjusted_severity' => $validatedData['severity_adjustment'] ?? null,
                'priority_level' => $validatedData['priority_level'] ?? null,
                'admin_user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ];
            $report->metadata = $metadata;

            $report->save();

            // Format response using data mapper
            $responseData = $this->dataMapper->mapUnifiedToWebResponse($report->load(['reporter', 'images']));

            return response()->json([
                'status' => 'success',
                'message' => 'Disaster report verified successfully',
                'data' => $responseData,
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Disaster report not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify disaster report',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Publish a verified disaster report
     */
    public function publish(Request $request, $id)
    {
        try {
            $report = DisasterReport::findOrFail($id);

            if ($report->status !== 'VERIFIED') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only verified reports can be published',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'public_summary' => 'nullable|string|max:500',
                'publish_level' => 'required|in:public,restricted,internal',
                'emergency_alert' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $report->status = 'PUBLISHED';

            // Add publishing metadata
            $metadata = $report->metadata ?? [];
            $metadata['publication'] = [
                'published_by' => auth()->id(),
                'published_at' => now()->toISOString(),
                'public_summary' => $request->public_summary,
                'publish_level' => $request->publish_level,
                'emergency_alert' => $request->boolean('emergency_alert', false),
            ];
            $report->metadata = $metadata;

            $report->save();

            return response()->json([
                'success' => true,
                'message' => 'Disaster report published successfully',
                'data' => $report->load(['reporter', 'images']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish disaster report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user statistics for dashboard
     */
    public function userStatistics(Request $request)
    {
        $user = auth()->user();
        /** @var \App\Models\User $user */
        $stats = [
            'total_reports' => $user->disasterReports()->count(),
            'reports_by_status' => [
                'pending' => $user->disasterReports()->where('status', 'PENDING')->count(),
                'verified' => $user->disasterReports()->where('status', 'VERIFIED')->count(),
                'published' => $user->disasterReports()->where('status', 'PUBLISHED')->count(),
                'resolved' => $user->disasterReports()->where('status', 'RESOLVED')->count(),
            ],
            'reports_by_severity' => [
                'low' => $user->disasterReports()->where('severity_level', 'LOW')->count(),
                'medium' => $user->disasterReports()->where('severity_level', 'MEDIUM')->count(),
                'high' => $user->disasterReports()->where('severity_level', 'HIGH')->count(),
                'critical' => $user->disasterReports()->where('severity_level', 'CRITICAL')->count(),
            ],
            'recent_activity' => $user->disasterReports()
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'status', 'severity_level', 'created_at']),
            'monthly_reports' => $user->disasterReports()
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'User statistics retrieved successfully',
            'data' => $stats,
        ]);
    }

    /**
     * Get admin summary statistics
     */
    private function getAdminSummary()
    {
        return [
            'total_reports' => DisasterReport::count(),
            'pending_review' => DisasterReport::where('status', 'PENDING')->count(),
            'verified_reports' => DisasterReport::where('status', 'VERIFIED')->count(),
            'published_reports' => DisasterReport::where('status', 'PUBLISHED')->count(),
            'critical_reports' => DisasterReport::where('severity_level', 'CRITICAL')->count(),
            'reports_today' => DisasterReport::whereDate('created_at', today())->count(),
            'reports_this_week' => DisasterReport::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'reports_this_month' => DisasterReport::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'disaster_types' => DisasterReport::selectRaw('disaster_type, COUNT(*) as count')
                ->groupBy('disaster_type')
                ->pluck('count', 'disaster_type')
                ->toArray(),
        ];
    }
}
