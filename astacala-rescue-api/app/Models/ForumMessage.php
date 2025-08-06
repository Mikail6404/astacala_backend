<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'disaster_report_id',
        'user_id',
        'parent_message_id',
        'message',
        'message_type',
        'priority_level',
        'is_read',
        'edited_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'edited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $with = ['user'];

    /**
     * Get the disaster report that owns this message
     */
    public function disasterReport(): BelongsTo
    {
        return $this->belongsTo(DisasterReport::class);
    }

    /**
     * Get the user who posted this message
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent message if this is a reply
     */
    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(ForumMessage::class, 'parent_message_id');
    }

    /**
     * Get all replies to this message
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ForumMessage::class, 'parent_message_id')
            ->with(['user', 'replies'])
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get all messages in a disaster report thread
     */
    public function scopeForDisasterReport($query, $disasterReportId)
    {
        return $query->where('disaster_report_id', $disasterReportId);
    }

    /**
     * Get only top-level messages (not replies)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_message_id');
    }

    /**
     * Get messages by priority level
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority_level', $priority);
    }

    /**
     * Mark message as read
     */
    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Check if message is emergency priority
     */
    public function isEmergency(): bool
    {
        return $this->priority_level === 'emergency';
    }

    /**
     * Get formatted timestamp for Indonesian locale
     */
    public function getFormattedTimestampAttribute(): string
    {
        return $this->created_at->locale('id')->format('d F Y H:i').' WIB';
    }
}
