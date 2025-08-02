<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Publication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'type',
        'category',
        'tags',
        'featured_image',
        'status',
        'author_id',
        'published_at',
        'published_by',
        'updated_by',
        'archived_at',
        'archived_by',
        'view_count',
        'meta_description',
        'slug'
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    protected $dates = [
        'published_at',
        'archived_at',
        'deleted_at'
    ];

    /**
     * Get the author of the publication
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the user who published this publication
     */
    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Get the user who last updated this publication
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who archived this publication
     */
    public function archiver()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Get the disaster reports related to this publication
     */
    public function reports()
    {
        return $this->belongsToMany(DisasterReport::class, 'publication_disaster_reports');
    }

    /**
     * Get the comments for this publication
     */
    public function comments()
    {
        return $this->hasMany(PublicationComment::class);
    }

    /**
     * Scope a query to only include published publications
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope a query to only include draft publications
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include archived publications
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope a query to filter by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by category
     */
    public function scopeInCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get the publication's formatted publish date
     */
    public function getFormattedPublishedAtAttribute()
    {
        return $this->published_at ? $this->published_at->format('Y-m-d H:i:s') : null;
    }

    /**
     * Get the publication's excerpt (first 150 characters of content)
     */
    public function getExcerptAttribute()
    {
        return strlen($this->content) > 150
            ? substr(strip_tags($this->content), 0, 150) . '...'
            : strip_tags($this->content);
    }

    /**
     * Get the publication's estimated reading time
     */
    public function getReadingTimeAttribute()
    {
        $wordCount = str_word_count(strip_tags($this->content));
        return ceil($wordCount / 200); // Average reading speed: 200 words per minute
    }

    /**
     * Check if publication is published
     */
    public function isPublished()
    {
        return $this->status === 'published'
            && $this->published_at
            && $this->published_at <= now();
    }

    /**
     * Check if publication is draft
     */
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    /**
     * Check if publication is archived
     */
    public function isArchived()
    {
        return $this->status === 'archived';
    }

    /**
     * Get related publications based on category and tags
     */
    public function getRelatedPublications($limit = 5)
    {
        return static::published()
            ->where('id', '!=', $this->id)
            ->where(function ($query) {
                $query->where('category', $this->category);

                if ($this->tags) {
                    $tags = explode(',', $this->tags);
                    foreach ($tags as $tag) {
                        $query->orWhere('tags', 'LIKE', '%' . trim($tag) . '%');
                    }
                }
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
