<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient_id', // Keep for backward compatibility
        'title',
        'message',
        'type',
        'priority',
        'related_report_id',
        'action_url',
        'is_read',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get the user who should receive this notification.
     */
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Get the user who should receive this notification (new method).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the related disaster report.
     */
    public function relatedReport()
    {
        return $this->belongsTo(DisasterReport::class, 'related_report_id');
    }
}
