<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'image_url',
        'thumbnail_url',
        'caption',
        'file_size',
        'upload_order',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'upload_order' => 'integer',
        ];
    }

    /**
     * Get the disaster report that owns this image.
     */
    public function disasterReport()
    {
        return $this->belongsTo(DisasterReport::class, 'report_id');
    }
}
