<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsArticle extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            if (empty($row->slug) && !empty($row->title)) {
                $row->slug = self::uniqueSlug($row->title);
            }
        });
    }

    protected static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'article-' . now()->timestamp;
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    protected $fillable = [
        'title', 'slug', 'category', 'excerpt', 'body',
        'image_path', 'image_gradient',
        'is_featured', 'is_published', 'published_at',
        'view_count', 'author_id',
    ];

    protected $casts = [
        'is_featured'  => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'view_count'   => 'integer',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($q)
    {
        return $q->where('is_published', true)
            ->where(function ($w) {
                $w->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function scopeFeatured($q)
    {
        return $q->where('is_featured', true);
    }
}
