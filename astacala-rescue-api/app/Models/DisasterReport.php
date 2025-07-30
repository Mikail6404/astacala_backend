<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisasterReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'disaster_type',
        'severity_level',
        'status',
        'latitude',
        'longitude',
        'location_name',
        'address',
        'estimated_affected',
        'weather_condition',
        'team_name',
        'reported_by',
        'assigned_to',
        'metadata',
        'incident_timestamp',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'incident_timestamp' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'estimated_affected' => 'integer',
        ];
    }

    /**
     * Get the user who reported this disaster.
     */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Get the user assigned to this disaster.
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the images for this disaster report.
     */
    public function images()
    {
        return $this->hasMany(ReportImage::class, 'report_id');
    }

    /**
     * Get notifications related to this report.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'related_report_id');
    }

    /**
     * Get forum messages for this disaster report.
     */
    public function forumMessages()
    {
        return $this->hasMany(ForumMessage::class);
    }

    /**
     * Get the latest forum message for this report.
     */
    public function latestForumMessage()
    {
        return $this->hasOne(ForumMessage::class)->latest();
    }
}
