<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * Cross-Platform File Storage Service
 * Handles unified file upload, validation, and storage for both mobile and web platforms
 */
class CrossPlatformFileStorageService
{
    // File size limits (in bytes)
    const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
    const MAX_DOCUMENT_SIZE = 20 * 1024 * 1024; // 20MB
    const MAX_AVATAR_SIZE = 5 * 1024 * 1024; // 5MB

    // Image dimensions for optimization
    const MAX_IMAGE_WIDTH = 1920;
    const MAX_IMAGE_HEIGHT = 1080;
    const THUMBNAIL_WIDTH = 300;
    const THUMBNAIL_HEIGHT = 200;
    const AVATAR_SIZE = 200;

    // Allowed file types
    const ALLOWED_IMAGE_TYPES = ['jpeg', 'jpg', 'png', 'webp', 'gif'];
    const ALLOWED_DOCUMENT_TYPES = ['pdf', 'doc', 'docx', 'txt'];

    // Storage paths
    const DISASTER_IMAGES_PATH = 'disaster-reports';
    const AVATARS_PATH = 'avatars';
    const DOCUMENTS_PATH = 'documents';
    const THUMBNAILS_PATH = 'thumbnails';

    private $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Store disaster report image with platform-specific handling
     */
    public function storeDisasterImage(UploadedFile $file, int $reportId, string $platform = 'mobile'): array
    {
        try {
            // Validate image file
            $this->validateImageFile($file);

            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $reportId, 'image');

            // Create storage path
            $path = self::DISASTER_IMAGES_PATH . "/{$reportId}";

            // Process and store original image
            $originalPath = $this->processAndStoreImage($file, $path, $filename);

            // Generate thumbnail
            $thumbnailPath = $this->generateThumbnail($file, $path, $filename);

            // Get file info
            $fileInfo = $this->getFileInfo($file, $originalPath);

            Log::info("Disaster image stored successfully", [
                'report_id' => $reportId,
                'platform' => $platform,
                'original_path' => $originalPath,
                'thumbnail_path' => $thumbnailPath,
                'file_size' => $fileInfo['size']
            ]);

            return [
                'success' => true,
                'original_path' => $originalPath,
                'thumbnail_path' => $thumbnailPath,
                'public_url' => Storage::url($originalPath),
                'thumbnail_url' => Storage::url($thumbnailPath),
                'filename' => $filename,
                'file_info' => $fileInfo,
                'metadata' => [
                    'platform' => $platform,
                    'report_id' => $reportId,
                    'upload_timestamp' => now()->toIso8601String()
                ]
            ];
        } catch (\Exception $e) {
            Log::error("Failed to store disaster image", [
                'report_id' => $reportId,
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Store user avatar with optimization
     */
    public function storeUserAvatar(UploadedFile $file, int $userId, string $platform = 'mobile'): array
    {
        try {
            // Validate avatar file
            $this->validateAvatarFile($file);

            // Generate filename
            $filename = "avatar_{$userId}." . $file->getClientOriginalExtension();
            $path = self::AVATARS_PATH . "/{$filename}";

            // Process and optimize avatar
            $optimizedPath = $this->processAndStoreAvatar($file, $path);

            // Get file info
            $fileInfo = $this->getFileInfo($file, $optimizedPath);

            Log::info("User avatar stored successfully", [
                'user_id' => $userId,
                'platform' => $platform,
                'path' => $optimizedPath,
                'file_size' => $fileInfo['size']
            ]);

            return [
                'success' => true,
                'path' => $optimizedPath,
                'public_url' => Storage::url($optimizedPath),
                'filename' => $filename,
                'file_info' => $fileInfo,
                'metadata' => [
                    'platform' => $platform,
                    'user_id' => $userId,
                    'upload_timestamp' => now()->toIso8601String()
                ]
            ];
        } catch (\Exception $e) {
            Log::error("Failed to store user avatar", [
                'user_id' => $userId,
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Store document file with validation
     */
    public function storeDocument(UploadedFile $file, int $reportId, string $platform = 'mobile'): array
    {
        try {
            // Validate document file
            $this->validateDocumentFile($file);

            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $reportId, 'document');

            // Create storage path
            $path = self::DOCUMENTS_PATH . "/{$reportId}/{$filename}";

            // Store document
            $storedPath = Storage::disk('public')->putFileAs(
                self::DOCUMENTS_PATH . "/{$reportId}",
                $file,
                $filename
            );

            // Get file info
            $fileInfo = $this->getFileInfo($file, $storedPath);

            Log::info("Document stored successfully", [
                'report_id' => $reportId,
                'platform' => $platform,
                'path' => $storedPath,
                'file_size' => $fileInfo['size']
            ]);

            return [
                'success' => true,
                'path' => $storedPath,
                'public_url' => Storage::url($storedPath),
                'filename' => $filename,
                'file_info' => $fileInfo,
                'metadata' => [
                    'platform' => $platform,
                    'report_id' => $reportId,
                    'upload_timestamp' => now()->toIso8601String()
                ]
            ];
        } catch (\Exception $e) {
            Log::error("Failed to store document", [
                'report_id' => $reportId,
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete file and its variants (thumbnails, etc.)
     */
    public function deleteFile(string $filePath): bool
    {
        try {
            $deleted = false;

            // Delete main file
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
                $deleted = true;
            }

            // Delete thumbnail if it exists
            $thumbnailPath = $this->getThumbnailPath($filePath);
            if (Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            Log::info("File deleted successfully", [
                'file_path' => $filePath,
                'thumbnail_path' => $thumbnailPath
            ]);

            return $deleted;
        } catch (\Exception $e) {
            Log::error("Failed to delete file", [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get file information and metadata
     */
    public function getFileMetadata(string $filePath): array
    {
        try {
            if (!Storage::disk('public')->exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            $fullPath = Storage::disk('public')->path($filePath);
            $fileSize = Storage::disk('public')->size($filePath);
            $mimeType = mime_content_type(Storage::disk('public')->path($filePath));
            $lastModified = Storage::disk('public')->lastModified($filePath);

            $metadata = [
                'path' => $filePath,
                'full_path' => $fullPath,
                'public_url' => Storage::url($filePath),
                'file_size' => $fileSize,
                'file_size_human' => $this->formatFileSize($fileSize),
                'mime_type' => $mimeType,
                'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
                'last_modified' => date('Y-m-d H:i:s', $lastModified),
                'exists' => true
            ];

            // Add image-specific metadata if it's an image
            if ($this->isImageFile($mimeType)) {
                $dimensions = $this->getImageDimensions($fullPath);
                $metadata = array_merge($metadata, $dimensions);
            }

            return $metadata;
        } catch (\Exception $e) {
            return [
                'path' => $filePath,
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate image file
     */
    private function validateImageFile(UploadedFile $file): void
    {
        $validator = Validator::make([
            'file' => $file
        ], [
            'file' => [
                'required',
                'file',
                'mimes:' . implode(',', self::ALLOWED_IMAGE_TYPES),
                'max:' . (self::MAX_IMAGE_SIZE / 1024) // Convert to KB for validation
            ]
        ]);

        if ($validator->fails()) {
            throw new \Exception('Image validation failed: ' . implode(', ', $validator->errors()->all()));
        }
    }

    /**
     * Validate avatar file
     */
    private function validateAvatarFile(UploadedFile $file): void
    {
        $validator = Validator::make([
            'file' => $file
        ], [
            'file' => [
                'required',
                'file',
                'mimes:' . implode(',', self::ALLOWED_IMAGE_TYPES),
                'max:' . (self::MAX_AVATAR_SIZE / 1024) // Convert to KB for validation
            ]
        ]);

        if ($validator->fails()) {
            throw new \Exception('Avatar validation failed: ' . implode(', ', $validator->errors()->all()));
        }
    }

    /**
     * Validate document file
     */
    private function validateDocumentFile(UploadedFile $file): void
    {
        $validator = Validator::make([
            'file' => $file
        ], [
            'file' => [
                'required',
                'file',
                'mimes:' . implode(',', self::ALLOWED_DOCUMENT_TYPES),
                'max:' . (self::MAX_DOCUMENT_SIZE / 1024) // Convert to KB for validation
            ]
        ]);

        if ($validator->fails()) {
            throw new \Exception('Document validation failed: ' . implode(', ', $validator->errors()->all()));
        }
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file, int $id, string $type): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('YmdHis');
        $uuid = Str::uuid()->toString();

        return "{$type}_{$id}_{$timestamp}_{$uuid}.{$extension}";
    }

    /**
     * Process and store image with optimization
     */
    private function processAndStoreImage(UploadedFile $file, string $path, string $filename): string
    {
        // Read and optimize image
        $image = $this->imageManager->read($file->getRealPath());

        // Resize if too large
        if ($image->width() > self::MAX_IMAGE_WIDTH || $image->height() > self::MAX_IMAGE_HEIGHT) {
            $image->scale(width: self::MAX_IMAGE_WIDTH, height: self::MAX_IMAGE_HEIGHT);
        }

        // Save optimized image
        $fullPath = storage_path('app/public/' . $path . '/' . $filename);

        // Ensure directory exists
        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        // Save with quality optimization
        $image->save($fullPath, quality: 85);

        return $path . '/' . $filename;
    }

    /**
     * Generate thumbnail for image
     */
    private function generateThumbnail(UploadedFile $file, string $path, string $filename): string
    {
        $thumbnailFilename = 'thumb_' . $filename;
        $thumbnailPath = self::THUMBNAILS_PATH . '/' . $path . '/' . $thumbnailFilename;

        // Read and resize image for thumbnail
        $image = $this->imageManager->read($file->getRealPath());
        $image->scale(width: self::THUMBNAIL_WIDTH, height: self::THUMBNAIL_HEIGHT);

        // Save thumbnail
        $fullThumbnailPath = storage_path('app/public/' . $thumbnailPath);

        // Ensure directory exists
        if (!file_exists(dirname($fullThumbnailPath))) {
            mkdir(dirname($fullThumbnailPath), 0755, true);
        }

        $image->save($fullThumbnailPath, quality: 80);

        return $thumbnailPath;
    }

    /**
     * Process and store avatar with optimization
     */
    private function processAndStoreAvatar(UploadedFile $file, string $path): string
    {
        // Read and optimize avatar
        $image = $this->imageManager->read($file->getRealPath());

        // Crop to square and resize
        $image->cover(self::AVATAR_SIZE, self::AVATAR_SIZE);

        // Save optimized avatar
        $fullPath = storage_path('app/public/' . $path);

        // Ensure directory exists
        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $image->save($fullPath, quality: 90);

        return $path;
    }

    /**
     * Get file information
     */
    private function getFileInfo(UploadedFile $file, string $storedPath): array
    {
        return [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'size_human' => $this->formatFileSize($file->getSize()),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'stored_path' => $storedPath
        ];
    }

    /**
     * Get thumbnail path for a given file path
     */
    private function getThumbnailPath(string $filePath): string
    {
        $pathInfo = pathinfo($filePath);
        return self::THUMBNAILS_PATH . '/' . $pathInfo['dirname'] . '/thumb_' . $pathInfo['basename'];
    }

    /**
     * Check if file is an image
     */
    private function isImageFile(string $mimeType): bool
    {
        return strpos($mimeType, 'image/') === 0;
    }

    /**
     * Get image dimensions
     */
    private function getImageDimensions(string $fullPath): array
    {
        try {
            $imageSize = getimagesize($fullPath);
            return [
                'width' => $imageSize[0] ?? null,
                'height' => $imageSize[1] ?? null,
                'aspect_ratio' => $imageSize[0] && $imageSize[1] ? round($imageSize[0] / $imageSize[1], 2) : null
            ];
        } catch (\Exception $e) {
            return [
                'width' => null,
                'height' => null,
                'aspect_ratio' => null
            ];
        }
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($size, 1024));
        return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Get storage statistics
     */
    public function getStorageStatistics(): array
    {
        try {
            $stats = [];

            // Get statistics for each storage path
            $paths = [
                'disaster_images' => self::DISASTER_IMAGES_PATH,
                'avatars' => self::AVATARS_PATH,
                'documents' => self::DOCUMENTS_PATH,
                'thumbnails' => self::THUMBNAILS_PATH
            ];

            foreach ($paths as $key => $path) {
                $files = Storage::disk('public')->allFiles($path);
                $totalSize = 0;

                foreach ($files as $file) {
                    $totalSize += Storage::disk('public')->size($file);
                }

                $stats[$key] = [
                    'file_count' => count($files),
                    'total_size' => $totalSize,
                    'total_size_human' => $this->formatFileSize($totalSize)
                ];
            }

            // Overall statistics
            $totalFiles = array_sum(array_column($stats, 'file_count'));
            $totalSize = array_sum(array_column($stats, 'total_size'));

            return [
                'by_category' => $stats,
                'overall' => [
                    'total_files' => $totalFiles,
                    'total_size' => $totalSize,
                    'total_size_human' => $this->formatFileSize($totalSize)
                ],
                'generated_at' => now()->toIso8601String()
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get storage statistics", [
                'error' => $e->getMessage()
            ]);

            return [
                'error' => 'Failed to retrieve storage statistics',
                'generated_at' => now()->toIso8601String()
            ];
        }
    }
}
