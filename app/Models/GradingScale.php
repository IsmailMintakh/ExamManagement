<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GradingScale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'school_id', 'is_default', 'is_active'];

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

    public function entries()
    {
        return $this->hasMany(GradingScaleEntry::class)->orderBy('min_percentage', 'desc');
    }

    public function getGrade(float $percentage): ?GradingScaleEntry
    {
        return $this->entries->first(function ($entry) use ($percentage) {
            return $percentage >= $entry->min_percentage && $percentage <= $entry->max_percentage;
        });
    }
}
