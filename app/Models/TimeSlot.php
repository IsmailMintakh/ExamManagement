<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single bell-schedule slot — a period, break, lunch, or assembly. Belongs to
 * a school so different schools in the district can run different schedules.
 *
 * The `weekdays` JSON field carries which days this slot is active. Default
 * is all six days; Friday-half-day schools opt OUT of late periods by
 * dropping 'fri' from the array (e.g. Period 5 weekdays: ['mon','tue','wed',
 * 'thu','sat']). The viewer logic asks "does this slot apply to {day}?"
 * before rendering the cell.
 */
class TimeSlot extends Model
{
    protected $fillable = [
        'school_id', 'name', 'type', 'stage', 'starts_at', 'ends_at', 'sort_order', 'weekdays',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** Default for newly-created slots — every weekday Mon–Sat. */
    public const ALL_WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function entries()
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * Does this slot run on the given day? Null/empty `weekdays` means "all
     * days" so legacy rows behave sanely. Used in every render path that
     * walks the bell schedule.
     */
    public function appliesTo(string $day): bool
    {
        $days = $this->weekdays ?: self::ALL_WEEKDAYS;
        return in_array($day, $days, true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Slots applicable to a given class stage:
     *   - stage IS NULL (school-wide default), OR
     *   - stage = the class's stage
     * If $stage is null, returns only the school-wide rows.
     */
    public function scopeForStage($query, ?string $stage)
    {
        return $query->where(function ($q) use ($stage) {
            $q->whereNull('stage');
            if ($stage) {
                $q->orWhere('stage', $stage);
            }
        });
    }

    /**
     * Slots a section actually uses on its timetable:
     *  - prefer the rows tagged with its class's stage
     *  - fall back to the school-wide default (stage IS NULL) when the
     *    stage has no rows configured
     * Returns an ordered collection ready for rendering.
     */
    public static function forSection(\App\Models\Section $section): \Illuminate\Support\Collection
    {
        $schoolId = $section->schoolClass?->school_id;
        $stage = $section->schoolClass?->stage;
        if (!$schoolId) {
            return collect();
        }

        if ($stage) {
            $stageSlots = self::where('school_id', $schoolId)->where('stage', $stage)
                ->ordered()->get();
            if ($stageSlots->isNotEmpty()) {
                return $stageSlots;
            }
        }
        return self::where('school_id', $schoolId)->whereNull('stage')->ordered()->get();
    }

    public function scopeForDay($query, string $day)
    {
        // SQLite/MySQL: JSON_CONTAINS works on both via the JSON cast we
        // already declared. For null weekdays we treat as "applies to all".
        return $query->where(function ($q) use ($day) {
            $q->whereNull('weekdays')
              ->orWhereJsonContains('weekdays', $day);
        });
    }
}
