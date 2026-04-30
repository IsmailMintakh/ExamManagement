<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'name', 'type', 'title_text', 'body_text',
        'primary_color', 'accent_color', 'orientation', 'border_style',
        'design_layout', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public static function getDefaultForType(string $type, ?int $schoolId = null): ?self
    {
        // Prefer school-specific default, else global default
        if ($schoolId) {
            $specific = self::where('type', $type)
                ->where('school_id', $schoolId)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
            if ($specific) return $specific;
        }

        return self::where('type', $type)
            ->whereNull('school_id')
            ->where('is_default', true)
            ->where('is_active', true)
            ->first()
            ?? self::where('type', $type)->where('is_active', true)->first();
    }
}
