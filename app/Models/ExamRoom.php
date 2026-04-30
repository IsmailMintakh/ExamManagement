<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamRoom extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'name', 'capacity', 'rows', 'cols', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function seats()
    {
        return $this->hasMany(ExamSeat::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
