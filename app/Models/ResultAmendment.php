<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit-log row for a single amendment to a published result. Immutable —
 * we never update or delete these once written. Multiple amendments per
 * result are allowed (a result might be corrected twice if the recheck
 * surfaces a second error).
 */
class ResultAmendment extends Model
{
    protected $fillable = [
        'result_id', 'amended_by', 'reason', 'before', 'after',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function result()
    {
        return $this->belongsTo(Result::class);
    }

    public function amendedBy()
    {
        return $this->belongsTo(User::class, 'amended_by');
    }
}
