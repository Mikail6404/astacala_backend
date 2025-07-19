<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_id',
        'title',
        'message',
        'type',
        'priority',
        'related_report_id',
        'action_url',
        'is_read',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
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
     * Get the related disaster report.
     */
    public function relatedReport()
    {
        return $this->belongsTo(DisasterReport::class, 'related_report_id');
    }
}
