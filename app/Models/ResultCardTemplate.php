<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResultCardTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'academic_session_id',
        'school_id',
        'html_template',
        'placeholders',
        'header_image',
        'footer_text',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default template for an academic session.
     * If $schoolId is provided, prefers a school-specific default,
     * then falls back to a session-wide default (school_id = null).
     */
    public static function getDefaultForSession($sessionId, $schoolId = null): ?self
    {
        if ($schoolId) {
            $schoolSpecific = static::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->where('academic_session_id', $sessionId)
                ->where('school_id', $schoolId)
                ->first();

            if ($schoolSpecific) {
                return $schoolSpecific;
            }
        }

        return static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->where('academic_session_id', $sessionId)
            ->whereNull('school_id')
            ->first();
    }
}
