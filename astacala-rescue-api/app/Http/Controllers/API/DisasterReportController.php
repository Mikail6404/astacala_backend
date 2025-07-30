<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DisasterReport;
use App\Models\ReportImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DisasterReportController extends Controller
{
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
                ]
            ]
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
                'errors' => $validator->errors()
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
            ]
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

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully',
            'data' => [
                'reportId' => $report->id,
                'status' => $report->status,
                'submittedAt' => $report->created_at,
                'imageUrls' => $imageUrls,
            ]
        ], 201);
    }

    /**
     * Display the specified disaster report.
     */
    public function show(string $id)
    {
        $report = DisasterReport::with(['reporter', 'assignee', 'images'])
            ->find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Update the specified disaster report.
     */
    public function update(Request $request, string $id)
    {
        $report = DisasterReport::find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found'
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
                'errors' => $validator->errors()
            ], 422);
        }

        $report->update($request->only(['status', 'assigned_to']));

        return response()->json([
            'success' => true,
            'message' => 'Report updated successfully',
            'data' => $report
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
            ]
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
            ]
        ]);
    }

    /**
     * Submit a disaster report from web application
     * Cross-platform compatibility endpoint
     */
    public function webSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'disaster_type' => 'required|string|in:EARTHQUAKE,FLOOD,FIRE,HURRICANE,TSUNAMI,LANDSLIDE,VOLCANO,DROUGHT,BLIZZARD,TORNADO,OTHER',
            'severity_level' => 'required|in:LOW,MEDIUM,HIGH,CRITICAL',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'estimated_affected' => 'nullable|integer|min:0',
            'weather_condition' => 'nullable|string',
            'incident_timestamp' => 'required|date',
            'images' => 'nullable|array|max:5',
            'images.*' => 'url', // For web, we expect image URLs
            'reporter_contact' => 'nullable|string|max:255',
            'emergency_level' => 'nullable|in:LOW,MEDIUM,HIGH,CRITICAL'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = new DisasterReport();
            $report->title = $request->title;
            $report->description = $request->description;
            $report->disaster_type = $request->disaster_type;
            $report->severity_level = $request->severity_level;
            $report->latitude = $request->latitude;
            $report->longitude = $request->longitude;
            $report->location_name = $request->location_name;
            $report->address = $request->address;
            $report->estimated_affected = $request->estimated_affected;
            $report->weather_condition = $request->weather_condition;
            $report->incident_timestamp = $request->incident_timestamp;
            $report->reported_by = auth()->id();
            $report->status = 'PENDING';
            
            // Add web-specific metadata
            $metadata = [
                'source' => 'web_dashboard',
                'submission_platform' => 'web',
                'reporter_contact' => $request->reporter_contact,
                'emergency_level' => $request->emergency_level ?? $request->severity_level,
                'submission_time' => now()->toISOString(),
                'user_agent' => $request->header('User-Agent')
            ];
            
            $report->metadata = $metadata;
            $report->save();

            // Handle image URLs for web submissions
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $imageUrl) {
                    if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                        ReportImage::create([
                            'disaster_report_id' => $report->id,
                            'image_path' => $imageUrl,
                            'is_primary' => false,
                            'uploaded_by' => auth()->id()
                        ]);
                    }
                }
            }

            $report->load(['reporter', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Disaster report submitted successfully from web dashboard',
                'data' => $report
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit disaster report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get disaster reports formatted for admin web dashboard
     */
    public function adminView(Request $request)
    {
        $query = DisasterReport::with(['reporter:id,name,email,phone', 'images'])
            ->select('*');

        // Admin-specific filters
        if ($request->has('status_filter')) {
            $statuses = explode(',', $request->status_filter);
            $query->whereIn('status', $statuses);
        }

        if ($request->has('severity_filter')) {
            $severities = explode(',', $request->severity_filter);
            $query->whereIn('severity_level', $severities);
        }

        if ($request->has('date_from')) {
            $query->whereDate('incident_timestamp', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('incident_timestamp', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('location_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('disaster_type', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'incident_timestamp');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $reports = $query->paginate($perPage);

        // Add computed fields for web dashboard
        $reports->getCollection()->transform(function ($report) {
            $report->time_since_incident = $report->incident_timestamp->diffForHumans();
            $report->reporter_info = [
                'name' => $report->reporter->name ?? 'Unknown',
                'email' => $report->reporter->email ?? 'N/A',
                'phone' => $report->reporter->phone ?? 'N/A'
            ];
            $report->image_count = $report->images->count();
            $report->needs_attention = in_array($report->status, ['PENDING', 'CRITICAL']) || 
                                     in_array($report->severity_level, ['HIGH', 'CRITICAL']);
            return $report;
        });

        return response()->json([
            'success' => true,
            'message' => 'Admin dashboard data retrieved successfully',
            'data' => [
                'reports' => $reports->items(),
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'total_pages' => $reports->lastPage(),
                    'total_reports' => $reports->total(),
                    'per_page' => $reports->perPage(),
                    'has_next_page' => $reports->hasMorePages(),
                    'has_previous_page' => $reports->currentPage() > 1,
                ],
                'summary' => $this->getAdminSummary()
            ]
        ]);
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
                ]
            ]
        ]);
    }

    /**
     * Verify a disaster report (admin action)
     */
    public function verify(Request $request, $id)
    {
        try {
            $report = DisasterReport::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'verification_notes' => 'nullable|string|max:1000',
                'severity_adjustment' => 'nullable|in:LOW,MEDIUM,HIGH,CRITICAL',
                'assign_team' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update report status and verification info
            $report->status = 'VERIFIED';
            
            if ($request->has('severity_adjustment')) {
                $report->severity_level = $request->severity_adjustment;
            }

            if ($request->has('assign_team')) {
                $report->assigned_to = $request->assign_team;
            }

            // Add verification metadata
            $metadata = $report->metadata ?? [];
            $metadata['verification'] = [
                'verified_by' => auth()->id(),
                'verified_at' => now()->toISOString(),
                'verification_notes' => $request->verification_notes,
                'original_severity' => $report->getOriginal('severity_level'),
                'adjusted_severity' => $request->severity_adjustment
            ];
            $report->metadata = $metadata;
            
            $report->save();

            return response()->json([
                'success' => true,
                'message' => 'Disaster report verified successfully',
                'data' => $report->load(['reporter', 'images'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify disaster report',
                'error' => $e->getMessage()
            ], 500);
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
                    'message' => 'Only verified reports can be published'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'public_summary' => 'nullable|string|max:500',
                'publish_level' => 'required|in:public,restricted,internal',
                'emergency_alert' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
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
                'emergency_alert' => $request->boolean('emergency_alert', false)
            ];
            $report->metadata = $metadata;
            
            $report->save();

            return response()->json([
                'success' => true,
                'message' => 'Disaster report published successfully',
                'data' => $report->load(['reporter', 'images'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish disaster report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics for dashboard
     */
    public function userStatistics(Request $request)
    {
        $user = auth()->user();
        
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
                ->count()
        ];

        return response()->json([
            'success' => true,
            'message' => 'User statistics retrieved successfully',
            'data' => $stats
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
                ->toArray()
        ];
    }
}
