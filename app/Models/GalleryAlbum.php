<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GalleryAlbum extends Model
{
    protected $fillable = [
        'title', 'slug', 'description', 'cover_image',
        'event_date', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'event_date' => 'date',
        'sort_order' => 'integer',
    ];

    protected $appends = ['cover_url'];

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_image ? Storage::url($this->cover_image) : null;
    }

    public function photos()
    {
        return $this->hasMany(GalleryPhoto::class, 'album_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('event_date')
            ->orderByDesc('id');
    }
}
