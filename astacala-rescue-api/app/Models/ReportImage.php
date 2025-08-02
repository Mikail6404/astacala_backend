<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'disaster_report_id',
        'image_path',
        'thumbnail_path',
        'original_filename',
        'file_size',
        'mime_type',
        'upload_order',
        'is_primary',
        'uploaded_by',
        'platform',
        'metadata',
        'caption',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'upload_order' => 'integer',
            'is_primary' => 'boolean',
            'uploaded_by' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the disaster report that owns this image.
     */
    public function disasterReport()
    {
        return $this->belongsTo(DisasterReport::class, 'disaster_report_id');
    }

    /**
     * Get the user who uploaded this image.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
