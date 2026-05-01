<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FacultyMember extends Model
{
    protected $fillable = [
        'name', 'designation', 'department', 'qualification',
        'photo_path', 'bio', 'email', 'phone',
        'years_experience', 'is_principal', 'is_featured',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_principal'     => 'boolean',
        'is_featured'      => 'boolean',
        'is_active'        => 'boolean',
        'sort_order'       => 'integer',
        'years_experience' => 'integer',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::url($this->photo_path) : null;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->orderByDesc('is_principal')
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
