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
}
