<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublicationComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'publication_id',
        'user_id',
        'comment',
        'parent_id',
        'status'
    ];

    protected $dates = [
        'deleted_at'
    ];

    /**
     * Get the publication this comment belongs to
     */
    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * Get the user who made this comment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment (for nested comments)
     */
    public function parent()
    {
        return $this->belongsTo(PublicationComment::class, 'parent_id');
    }

    /**
     * Get the child comments (replies)
     */
    public function replies()
    {
        return $this->hasMany(PublicationComment::class, 'parent_id');
    }

    /**
     * Scope a query to only include approved comments
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include top-level comments
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}
