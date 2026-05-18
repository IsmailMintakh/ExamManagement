<?php

namespace App\Http\Controllers;

use App\Models\SubstitutionAssignment;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal cover-duty inbox for teachers. Lists every substitution
 * assignment where they're the substitute_teacher_id, grouped by
 * today / upcoming / past.
 *
 * Teachers can self-decline a cover from here (admin still sees it
 * declined and reassigns).
 */
class MyCoversController extends Controller
{
    /** GET /my-covers */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $today = Carbon::today();
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

        $shape = function ($a) {
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
            ->values()
            ->map($shape);

        $upcomingRows = $rows->filter(fn ($a) => $a->date->isAfter($today))
            ->sortBy('date')
            ->values()
            ->map($shape);

        $pastRows = $rows->filter(fn ($a) => $a->date->isBefore($today))
            ->take(20)
            ->values()
            ->map($shape);

        return Inertia::render('MyAdjustments/Index', [
            'todayRows' => $todayRows,
            'upcomingRows' => $upcomingRows,
            'pastRows' => $pastRows,
            'totals' => [
                'today' => $todayRows->count(),
                'upcoming' => $upcomingRows->count(),
                'past' => $pastRows->count(),
                'all_time' => $rows->count(),
            ],
        ]);
    }

    /** POST /my-covers/{assignment}/decline — self-decline a cover. */
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
