<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PublicationController extends Controller
{
    /**
     * Display a listing of publications
     * Cross-platform endpoint for both mobile and web
     */
    public function index(Request $request)
    {
        try {
            $query = Publication::with(['author'])
                ->where('status', 'published')
                ->orderBy('published_at', 'desc');

            // Apply filters
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->has('category')) {
                $query->where('category', $request->input('category'));
            }

            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('tags', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Pagination
            $perPage = $request->input('per_page', 15);
            $publications = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Publications retrieved successfully',
                'data' => $publications,
                'meta' => [
                    'total' => $publications->total(),
                    'per_page' => $publications->perPage(),
                    'current_page' => $publications->currentPage(),
                    'last_page' => $publications->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve publications',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified publication
     * Cross-platform endpoint for both mobile and web
     */
    public function show($id)
    {
        try {
            $publication = Publication::with(['author'])->findOrFail($id);

            // Increment view count safely
            try {
                $publication->increment('view_count');
            } catch (\Exception $e) {
                // Ignore view count increment errors
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Publication retrieved successfully',
                'data' => $publication,
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Publication not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve publication',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created publication
     * Admin-only endpoint for web dashboard
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'type' => 'required|in:article,guide,announcement,report_summary',
                'category' => 'required|string|max:100',
                'tags' => 'nullable|string',
                'featured_image' => 'nullable|url',
                'status' => 'required|in:draft,published,archived',
                'related_report_ids' => 'nullable|array',
                'related_report_ids.*' => 'exists:disaster_reports,id',
                'publish_at' => 'nullable|date|after:now',
                'meta_description' => 'nullable|string|max:160',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $publication = new Publication;
            $publication->title = $request->input('title');
            $publication->content = $request->input('content');
            $publication->type = $request->input('type');
            $publication->category = $request->input('category');
            $publication->tags = $request->input('tags');
            $publication->featured_image = $request->input('featured_image');
            $publication->status = $request->input('status', 'draft');
            $publication->meta_description = $request->input('meta_description');
            $publication->author_id = Auth::id();

            // Set publish date
            if ($request->input('publish_at')) {
                $publication->published_at = Carbon::parse($request->input('publish_at'));
            } elseif ($request->input('status') === 'published') {
                $publication->published_at = now();
            }

            $publication->save();

            // Attach related reports if provided
            if ($request->has('related_report_ids')) {
                $publication->reports()->sync($request->input('related_report_ids'));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Publication created successfully',
                'data' => $publication->load(['author']),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create publication',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified publication
     * Admin-only endpoint for web dashboard
     */
    public function update(Request $request, $id)
    {
        try {
            $publication = Publication::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'type' => 'sometimes|required|in:article,guide,announcement,report_summary',
                'category' => 'sometimes|required|string|max:100',
                'tags' => 'nullable|string',
                'featured_image' => 'nullable|url',
                'status' => 'sometimes|required|in:draft,published,archived',
                'related_report_ids' => 'nullable|array',
                'related_report_ids.*' => 'exists:disaster_reports,id',
                'publish_at' => 'nullable|date',
                'meta_description' => 'nullable|string|max:160',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Update fields if provided
            if ($request->has('title')) {
                $publication->title = $request->input('title');
            }
            if ($request->has('content')) {
                $publication->content = $request->input('content');
            }
            if ($request->has('type')) {
                $publication->type = $request->input('type');
            }
            if ($request->has('category')) {
                $publication->category = $request->input('category');
            }
            if ($request->has('tags')) {
                $publication->tags = $request->input('tags');
            }
            if ($request->has('featured_image')) {
                $publication->featured_image = $request->input('featured_image');
            }
            if ($request->has('meta_description')) {
                $publication->meta_description = $request->input('meta_description');
            }

            // Handle status change
            if ($request->has('status')) {
                $oldStatus = $publication->status;
                $newStatus = $request->input('status');
                $publication->status = $newStatus;

                // Set published_at when publishing
                if ($oldStatus !== 'published' && $newStatus === 'published') {
                    $publication->published_at = $request->input('publish_at')
                        ? Carbon::parse($request->input('publish_at'))
                        : now();
                }
            }

            $publication->updated_by = Auth::id();
            $publication->save();

            // Update related reports if provided
            if ($request->has('related_report_ids')) {
                $publication->reports()->sync($request->input('related_report_ids'));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Publication updated successfully',
                'data' => $publication->load(['author']),
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Publication not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update publication',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified publication from storage
     * Admin-only endpoint for web dashboard
     */
    public function destroy($id)
    {
        try {
            $publication = Publication::findOrFail($id);

            // Soft delete or archive instead of permanent deletion
            $publication->status = 'archived';
            $publication->archived_at = now();
            $publication->archived_by = Auth::id();
            $publication->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Publication archived successfully',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Publication not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to archive publication',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Publish a draft publication
     * Admin-only endpoint for web dashboard
     */
    public function publish(Request $request, $id)
    {
        try {
            $publication = Publication::findOrFail($id);

            if ($publication->status === 'published') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Publication is already published',
                ], Response::HTTP_BAD_REQUEST);
            }

            $validator = Validator::make($request->all(), [
                'publish_at' => 'nullable|date|after_or_equal:now',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $publication->status = 'published';
            $publication->published_at = $request->input('publish_at')
                ? Carbon::parse($request->input('publish_at'))
                : now();
            $publication->published_by = Auth::id();
            $publication->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Publication published successfully',
                'data' => $publication->load(['author']),
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Publication not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to publish publication',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
