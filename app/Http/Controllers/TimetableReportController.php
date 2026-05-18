<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SubstitutionAssignment;
use App\Models\TimeSlot;
use App\Models\TimetableEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Three reports for the timetable subsystem:
 *
 *   1. Teacher load — periods/week + covers given/received per teacher
 *   2. Substitution fairness — cumulative covers given/received over a date
 *      window so admin can equalize over the term
 *   3. Class coverage gaps — sections with the most uncovered periods
 *      (chronic absenteeism / missing teachers)
 */
class TimetableReportController extends Controller
{
    /** GET /timetable/reports */
    public function index(Request $request): Response
    {
        $user = $request->user();
        // Reports are an admin tool — teachers monitor via /my-class.
        abort_unless($user->isSuperAdmin() || $user->isSchoolAdmin(), 403);
        $school = $this->resolveSchool($request);
        abort_if(!$school, 404);
        if (!$user->isSuperAdmin() && $user->school_id !== $school->id) abort(403);

        $type = $request->input('type', 'load');
        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))
            : Carbon::today()->startOfMonth();
        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))
            : Carbon::today();

        $payload = match ($type) {
            'fairness' => $this->fairnessReport($school, $from, $to),
            'coverage' => $this->coverageReport($school, $from, $to),
            default => $this->loadReport($school),
        };

        $allSchools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : [];

        return Inertia::render('Timetable/Reports', [
            'school' => ['id' => $school->id, 'name' => $school->name],
            'allSchools' => $allSchools,
            'currentSchoolId' => $school->id,
            'type' => $type,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'data' => $payload,
        ]);
    }

    protected function resolveSchool(Request $request): ?School
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            $sid = $request->integer('school_id');
            if ($sid) return School::find($sid);
            return School::active()->orderBy('name')->first();
        }
        return $user->school;
    }

    /**
     * Report 1 — Teacher load (current routine).
     * For each teacher: periods/week, distinct classes, distinct subjects,
     * and (so far this session) covers given vs covers received.
     */
    protected function loadReport(School $school): array
    {
        $sessionId = AcademicSession::currentSession()?->id;

        $teachers = User::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $entries = TimetableEntry::query()
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->whereIn('teacher_id', $teachers->pluck('id'))
            ->select(['teacher_id', 'school_class_id', 'subject_id', 'time_slot_id', 'weekday'])
            ->get();

        // Covers given (substitute) vs received (original) — session-scoped.
        $covers = SubstitutionAssignment::query()
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->whereIn('status', ['suggested', 'confirmed'])
            ->whereIn('substitute_teacher_id', $teachers->pluck('id'))
            ->orWhereIn('original_teacher_id', $teachers->pluck('id'))
            ->get(['substitute_teacher_id', 'original_teacher_id']);

        $rows = $teachers->map(function ($t) use ($entries, $covers) {
            $own = $entries->where('teacher_id', $t->id);
            return [
                'teacher_id' => $t->id,
                'teacher_name' => $t->name,
                'email' => $t->email,
                'periods_per_week' => $own->count(),
                'distinct_classes' => $own->pluck('school_class_id')->unique()->count(),
                'distinct_subjects' => $own->pluck('subject_id')->unique()->count(),
                'covers_given' => $covers->where('substitute_teacher_id', $t->id)->count(),
                'covers_received' => $covers->where('original_teacher_id', $t->id)->count(),
            ];
        })->sortByDesc('periods_per_week')->values()->all();

        return [
            'rows' => $rows,
            'summary' => [
                'total_teachers' => count($rows),
                'avg_periods' => count($rows) > 0
                    ? round(collect($rows)->avg('periods_per_week'), 1)
                    : 0,
                'max_periods' => count($rows) > 0
                    ? collect($rows)->max('periods_per_week')
                    : 0,
            ],
        ];
    }

    /**
     * Report 2 — Substitution fairness over the date window.
     * For each teacher: covers given, covers received, last cover date.
     * Sorted by covers_given desc so the over-loaded show up first.
     */
    protected function fairnessReport(School $school, Carbon $from, Carbon $to): array
    {
        $teachers = User::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher']))
            ->orderBy('name')
            ->get(['id', 'name']);

        $assigns = SubstitutionAssignment::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['suggested', 'confirmed'])
            ->get(['date', 'substitute_teacher_id', 'original_teacher_id']);

        $rows = $teachers->map(function ($t) use ($assigns) {
            $given = $assigns->where('substitute_teacher_id', $t->id);
            $received = $assigns->where('original_teacher_id', $t->id);
            return [
                'teacher_id' => $t->id,
                'teacher_name' => $t->name,
                'covers_given' => $given->count(),
                'covers_received' => $received->count(),
                'last_cover_given_on' => $given->max('date')?->toDateString(),
                'last_cover_received_on' => $received->max('date')?->toDateString(),
                'net_load' => $given->count() - $received->count(),
            ];
        })->sortByDesc('covers_given')->values()->all();

        return [
            'rows' => $rows,
            'summary' => [
                'total_covers' => $assigns->count(),
                'distinct_substitutes' => $assigns->pluck('substitute_teacher_id')->unique()->count(),
                'distinct_absent' => $assigns->pluck('original_teacher_id')->unique()->count(),
                'days_with_subs' => $assigns->pluck('date')->unique()->count(),
            ],
        ];
    }

    /**
     * Report 3 — Class coverage gaps.
     * For each section: how many of its teacher absences ended up uncovered
     * (substitute_teacher_id null OR status=declined). Highlights chronic
     * coverage gaps that hint at structural understaffing.
     */
    protected function coverageReport(School $school, Carbon $from, Carbon $to): array
    {
        // Pull every assignment in window for sections at this school.
        $assigns = SubstitutionAssignment::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('timetableEntry.schoolClass', fn ($q) => $q->where('school_id', $school->id))
            ->with([
                'timetableEntry.schoolClass:id,name',
                'timetableEntry.section:id,name',
            ])
            ->get();

        $bySection = $assigns->groupBy(fn ($a) => $a->timetableEntry?->section_id);

        $rows = $bySection->map(function ($rows, $sectionId) {
            if (!$sectionId) return null;
            $first = $rows->first();
            $declined = $rows->where('status', 'declined')->count();
            $suggested = $rows->where('status', 'suggested')->count();
            $confirmed = $rows->where('status', 'confirmed')->count();
            return [
                'section_id' => $sectionId,
                'class_name' => $first->timetableEntry?->schoolClass?->name,
                'section_name' => $first->timetableEntry?->section?->name,
                'total_periods_needing_cover' => $rows->count(),
                'covers_confirmed' => $confirmed,
                'covers_suggested' => $suggested,
                'covers_declined' => $declined,
                'gap_periods' => $declined, // declined = no one teaching = real gap
                'gap_pct' => $rows->count() > 0
                    ? round($declined / $rows->count() * 100, 1)
                    : 0,
            ];
        })->filter()->sortByDesc('gap_periods')->values()->all();

        return [
            'rows' => $rows,
            'summary' => [
                'total_periods_needing_cover' => $assigns->count(),
                'gap_periods' => $assigns->where('status', 'declined')->count(),
            ],
        ];
    }
}
