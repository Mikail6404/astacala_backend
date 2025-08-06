<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DisasterReport;
use App\Models\ReportImage;
use App\Models\User;
use App\Services\CrossPlatformFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Cross-Platform File Upload Controller
 * Handles unified file uploads for both mobile and web platforms
 */
class CrossPlatformFileUploadController extends Controller
{
    private CrossPlatformFileStorageService $fileStorageService;

    public function __construct(CrossPlatformFileStorageService $fileStorageService)
    {
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Upload disaster report images - unified endpoint for mobile and web
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadDisasterImages(Request $request, int $reportId)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'images' => 'required|array|min:1|max:10',
                'images.*' => 'required|file|mimes:jpeg,jpg,png,webp,gif|max:10240', // 10MB max
                'platform' => 'string|in:mobile,web',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Check if disaster report exists and user has permission
            $report = DisasterReport::find($reportId);
            if (! $report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Disaster report not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Check user permission
            if (! $this->canUploadToReport($report)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload files to this report',
                ], Response::HTTP_FORBIDDEN);
            }

            $platform = $request->input('platform', 'mobile');
            $uploadedImages = [];
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($request->file('images') as $index => $imageFile) {
                    // Store image using our file storage service
                    $result = $this->fileStorageService->storeDisasterImage(
                        $imageFile,
                        $reportId,
                        $platform
                    );

                    if ($result['success']) {
                        // Save to database
                        $reportImage = ReportImage::create([
                            'disaster_report_id' => $reportId,
                            'image_path' => $result['original_path'],
                            'thumbnail_path' => $result['thumbnail_path'],
                            'original_filename' => $result['file_info']['original_name'],
                            'file_size' => $result['file_info']['size'],
                            'mime_type' => $result['file_info']['mime_type'],
                            'upload_order' => $index + 1,
                            'is_primary' => $index === 0, // First image is primary
                            'uploaded_by' => Auth::id(),
                            'platform' => $platform,
                            'metadata' => json_encode($result['metadata']),
                        ]);

                        $uploadedImages[] = [
                            'id' => $reportImage->id,
                            'image_url' => $result['public_url'],
                            'thumbnail_url' => $result['thumbnail_url'],
                            'filename' => $result['filename'],
                            'file_size' => $result['file_info']['size'],
                            'file_size_human' => $result['file_info']['size_human'],
                            'is_primary' => $reportImage->is_primary,
                            'upload_order' => $reportImage->upload_order,
                        ];
                    } else {
                        $errors[] = [
                            'file_index' => $index,
                            'filename' => $imageFile->getClientOriginalName(),
                            'error' => $result['error'],
                        ];
                    }
                }

                DB::commit();

                // Update report's updated_at timestamp
                $report->touch();

                Log::info('Disaster images uploaded successfully', [
                    'report_id' => $reportId,
                    'platform' => $platform,
                    'uploaded_count' => count($uploadedImages),
                    'error_count' => count($errors),
                    'user_id' => Auth::id(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Images uploaded successfully',
                    'data' => [
                        'report_id' => $reportId,
                        'uploaded_images' => $uploadedImages,
                        'upload_count' => count($uploadedImages),
                        'errors' => $errors,
                        'platform' => $platform,
                    ],
                ], Response::HTTP_CREATED);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to upload disaster images', [
                'report_id' => $reportId,
                'platform' => $request->input('platform', 'mobile'),
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload user avatar - unified endpoint for mobile and web
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadUserAvatar(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'avatar' => 'required|file|mimes:jpeg,jpg,png,webp|max:5120', // 5MB max
                'platform' => 'string|in:mobile,web',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user = Auth::user();
            /** @var User $user */
            $platform = $request->input('platform', 'mobile');

            // Store avatar using our file storage service
            $result = $this->fileStorageService->storeUserAvatar(
                $request->file('avatar'),
                $user->id,
                $platform
            );

            if ($result['success']) {
                // Delete old avatar if exists
                if ($user->profile_picture_url) {
                    $this->fileStorageService->deleteFile($user->profile_picture_url);
                }

                // Update user avatar path
                $user->profile_picture_url = $result['path'];
                $user->save();

                Log::info('User avatar uploaded successfully', [
                    'user_id' => $user->id,
                    'platform' => $platform,
                    'avatar_path' => $result['path'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Avatar uploaded successfully',
                    'data' => [
                        'avatar_url' => $result['public_url'],
                        'filename' => $result['filename'],
                        'file_size' => $result['file_info']['size'],
                        'file_size_human' => $result['file_info']['size_human'],
                        'platform' => $platform,
                    ],
                ], Response::HTTP_OK);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload avatar',
                    'error' => $result['error'],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            Log::error('Failed to upload user avatar', [
                'user_id' => Auth::id(),
                'platform' => $request->input('platform', 'mobile'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload document for disaster report
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadDocument(Request $request, int $reportId)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'document' => 'required|file|mimes:pdf,doc,docx,txt|max:20480', // 20MB max
                'platform' => 'string|in:mobile,web',
                'document_type' => 'string|in:evidence,report,additional',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Check if disaster report exists and user has permission
            $report = DisasterReport::find($reportId);
            if (! $report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Disaster report not found',
                ], Response::HTTP_NOT_FOUND);
            }

            if (! $this->canUploadToReport($report)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload files to this report',
                ], Response::HTTP_FORBIDDEN);
            }

            $platform = $request->input('platform', 'mobile');

            // Store document using our file storage service
            $result = $this->fileStorageService->storeDocument(
                $request->file('document'),
                $reportId,
                $platform
            );

            if ($result['success']) {
                // Update report with document info (store in metadata)
                $metadata = $report->metadata ? json_decode($report->metadata, true) : [];
                $metadata['documents'] = $metadata['documents'] ?? [];
                $metadata['documents'][] = [
                    'path' => $result['path'],
                    'url' => $result['public_url'],
                    'filename' => $result['filename'],
                    'type' => $request->input('document_type', 'evidence'),
                    'file_size' => $result['file_info']['size'],
                    'uploaded_by' => Auth::id(),
                    'uploaded_at' => now()->toIso8601String(),
                    'platform' => $platform,
                ];

                $report->update(['metadata' => json_encode($metadata)]);

                Log::info('Document uploaded successfully', [
                    'report_id' => $reportId,
                    'platform' => $platform,
                    'document_path' => $result['path'],
                    'user_id' => Auth::id(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Document uploaded successfully',
                    'data' => [
                        'document_url' => $result['public_url'],
                        'filename' => $result['filename'],
                        'file_size' => $result['file_info']['size'],
                        'file_size_human' => $result['file_info']['size_human'],
                        'document_type' => $request->input('document_type', 'evidence'),
                        'platform' => $platform,
                    ],
                ], Response::HTTP_CREATED);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload document',
                    'error' => $result['error'],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            Log::error('Failed to upload document', [
                'report_id' => $reportId,
                'platform' => $request->input('platform', 'mobile'),
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete image from disaster report
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteImage(int $reportId, int $imageId)
    {
        try {
            $image = ReportImage::where('disaster_report_id', $reportId)
                ->where('id', $imageId)
                ->first();

            if (! $image) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Check permissions
            $report = $image->disasterReport;
            if (! $this->canUploadToReport($report)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this image',
                ], Response::HTTP_FORBIDDEN);
            }

            // Delete file from storage
            $fileDeleted = $this->fileStorageService->deleteFile($image->image_path);

            // Delete database record
            $image->delete();

            Log::info('Image deleted successfully', [
                'report_id' => $reportId,
                'image_id' => $imageId,
                'image_path' => $image->image_path,
                'file_deleted' => $fileDeleted,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
                'data' => [
                    'image_id' => $imageId,
                    'file_deleted' => $fileDeleted,
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to delete image', [
                'report_id' => $reportId,
                'image_id' => $imageId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get storage statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStorageStatistics()
    {
        try {
            $statistics = $this->fileStorageService->getStorageStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Storage statistics retrieved successfully',
                'data' => $statistics,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to get storage statistics', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve storage statistics',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check if current user can upload files to the given report
     */
    private function canUploadToReport(DisasterReport $report): bool
    {
        $user = Auth::user();

        // User can upload to their own reports
        if ($report->reported_by === $user->id) {
            return true;
        }

        // Admins can upload to any report (check if user has admin role)
        if ($user->role === 'admin' || $user->role === 'super_admin') {
            return true;
        }

        return false;
    }
}
