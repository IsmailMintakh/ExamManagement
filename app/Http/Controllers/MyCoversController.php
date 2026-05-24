<?php

namespace App\Http\Controllers;

use App\Models\SubstitutionAssignment;
use App\Models\TeacherAbsence;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal Class-Adjustment portal for teachers.
 *
 * Two perspectives in one page:
 *   - "Covers I take" — assignments where I'm the substitute (today / upcoming / past).
 *   - "Covers taken for me" — when I was absent, who covered each of my periods.
 *
 * Plus monthly fairness stats so a teacher sees their own load.
 */
class MyCoversController extends Controller
{
    /** GET /my-adjustments */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        // ── 1. Covers I take (this teacher = substitute) ──
        $rows = SubstitutionAssignment::query()
            ->where('substitute_teacher_id', $user->id)
            ->with([
                'timetableEntry.timeSlot',
                'timetableEntry.subject:id,name,code',
                'timetableEntry.schoolClass:id,name',
                'timetableEntry.section:id,name',
                'originalTeacher:id,name',
            ])
            ->orderBy('date', 'desc')
            ->get();

        $shapeTake = function ($a) {
            $te = $a->timetableEntry;
            return [
                'id' => $a->id,
                'date' => $a->date->toDateString(),
                'date_human' => $a->date->format('D, d M Y'),
                'time_slot' => $te?->timeSlot?->name,
                'time_range' => $te?->timeSlot
                    ? substr($te->timeSlot->starts_at, 0, 5) . '–' . substr($te->timeSlot->ends_at, 0, 5)
                    : null,
                'starts_at' => $te?->timeSlot ? substr($te->timeSlot->starts_at, 0, 5) : null,
                'class' => $te?->schoolClass?->name,
                'section' => $te?->section?->name,
                'subject' => $te?->subject?->name,
                'replaces' => $a->originalTeacher?->name,
                'status' => $a->status,
                'notes' => $a->notes,
            ];
        };

        $todayRows = $rows->filter(fn ($a) => $a->date->isSameDay($today))
            ->sortBy(fn ($a) => $a->timetableEntry?->timeSlot?->starts_at ?? '99:99')
            ->values()->map($shapeTake);

        $upcomingRows = $rows->filter(fn ($a) => $a->date->isAfter($today))
            ->sortBy('date')->values()->map($shapeTake);

        $pastRows = $rows->filter(fn ($a) => $a->date->isBefore($today))
            ->take(20)->values()->map($shapeTake);

        // ── 2. Covers taken FOR me (this teacher = original_teacher) ──
        // For every absence I've taken, show each period that was covered
        // (suggested / confirmed) so I know who handled my class.
        $myAbsenceCovers = SubstitutionAssignment::query()
            ->where('original_teacher_id', $user->id)
            ->with([
                'timetableEntry.timeSlot',
                'timetableEntry.subject:id,name,code',
                'timetableEntry.schoolClass:id,name',
                'timetableEntry.section:id,name',
                'substituteTeacher:id,name',
            ])
            ->orderBy('date', 'desc')
            ->get();

        $shapeAbsence = function ($a) {
            $te = $a->timetableEntry;
            return [
                'id' => $a->id,
                'date' => $a->date->toDateString(),
                'date_human' => $a->date->format('D, d M Y'),
                'time_slot' => $te?->timeSlot?->name,
                'time_range' => $te?->timeSlot
                    ? substr($te->timeSlot->starts_at, 0, 5) . '–' . substr($te->timeSlot->ends_at, 0, 5)
                    : null,
                'class' => $te?->schoolClass?->name,
                'section' => $te?->section?->name,
                'subject' => $te?->subject?->name,
                'covered_by' => $a->substituteTeacher?->name,
                'status' => $a->status,
            ];
        };

        $coveredForMeToday = $myAbsenceCovers->filter(fn ($a) => $a->date->isSameDay($today))
            ->sortBy(fn ($a) => $a->timetableEntry?->timeSlot?->starts_at ?? '99:99')
            ->values()->map($shapeAbsence);

        $coveredForMeUpcoming = $myAbsenceCovers->filter(fn ($a) => $a->date->isAfter($today))
            ->sortBy('date')->values()->map($shapeAbsence);

        $coveredForMePast = $myAbsenceCovers->filter(fn ($a) => $a->date->isBefore($today))
            ->take(20)->values()->map($shapeAbsence);

        // ── 3. Monthly fairness stats ──
        $thisMonthTakes = $rows->filter(fn ($a) => $a->date->between($monthStart, $monthEnd, true));
        $thisMonthAbsences = $myAbsenceCovers->filter(fn ($a) => $a->date->between($monthStart, $monthEnd, true));

        $byOriginalTeacher = $thisMonthTakes
            ->groupBy(fn ($a) => $a->original_teacher_id)
            ->map(fn ($g) => [
                'teacher_name' => $g->first()->originalTeacher?->name ?? '—',
                'count' => $g->count(),
            ])->values();

        $bySubstituteTeacher = $thisMonthAbsences
            ->groupBy(fn ($a) => $a->substitute_teacher_id)
            ->map(fn ($g) => [
                'teacher_name' => $g->first()->substituteTeacher?->name ?? '—',
                'count' => $g->count(),
            ])->values();

        return Inertia::render('MyAdjustments/Index', [
            // Existing — covers I take
            'todayRows' => $todayRows,
            'upcomingRows' => $upcomingRows,
            'pastRows' => $pastRows,
            // New — covers taken for me
            'absenceTodayRows' => $coveredForMeToday,
            'absenceUpcomingRows' => $coveredForMeUpcoming,
            'absencePastRows' => $coveredForMePast,
            // Monthly stats — used by the header strip
            'monthlyStats' => [
                'month_label' => Carbon::now()->format('F Y'),
                'covers_taken' => $thisMonthTakes->count(),
                'covered_for_me' => $thisMonthAbsences->count(),
                'by_original_teacher' => $byOriginalTeacher,
                'by_substitute_teacher' => $bySubstituteTeacher,
            ],
            'totals' => [
                'today' => $todayRows->count(),
                'upcoming' => $upcomingRows->count(),
                'past' => $pastRows->count(),
                'all_time' => $rows->count(),
            ],
        ]);
    }

    /** POST /my-adjustments/{assignment}/decline — self-decline a cover. */
    public function decline(Request $request, SubstitutionAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        if ($assignment->substitute_teacher_id !== $user->id) abort(403);

        $assignment->update([
            'status' => 'declined',
            'notes' => trim(($assignment->notes ? $assignment->notes . ' | ' : '') . 'Declined by teacher'),
        ]);

        return redirect()->back()->with('success', 'Cover declined. Admin has been notified.');
    }
}
