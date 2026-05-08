<?php

namespace App\Http\Controllers;

use App\Models\SubstitutionAssignment;
use App\Models\TeacherAbsence;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\SubstitutionAssignedNotification;
use App\Services\SubstitutionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily-absence + auto-substitute UI. Supplies the SubstitutionService with
 * its date input, persists absence rows, and renders the suggestions for
 * the admin to review / confirm / decline.
 */
class SubstitutionController extends Controller
{
    public function __construct(protected SubstitutionService $service) {}

    /** GET /timetable/substitutions?date=YYYY-MM-DD */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        // Teachers in this admin's school.
        $teachersQ = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher']))
            ->where('is_active', true)
            ->orderBy('name');
        if (!$user->isSuperAdmin()) {
            $teachersQ->where('school_id', $user->school_id);
        }
        $teachers = $teachersQ->get(['id', 'name']);

        $absences = TeacherAbsence::where('absent_on', $date->toDateString())
            ->whereIn('user_id', $teachers->pluck('id'))
            ->get(['user_id', 'reason', 'from_time']);
        $absentSet = $absences->keyBy('user_id');

        // Today's bell schedule — used by the UI to populate the "left after"
        // dropdown when admin marks a teacher as a partial-day absence.
        $schoolId = $user->isSuperAdmin() ? $teachers->first()?->school_id : $user->school_id;
        $todaySlots = $schoolId
            ? \App\Models\TimeSlot::where('school_id', $schoolId)
                ->where('type', 'period')
                ->orderBy('starts_at')
                ->get(['id', 'name', 'starts_at', 'ends_at'])
            : collect();

        $assignments = SubstitutionAssignment::where('date', $date->toDateString())
            ->with([
                'timetableEntry.timeSlot',
                'timetableEntry.subject',
                'timetableEntry.schoolClass',
                'timetableEntry.section',
                'originalTeacher:id,name',
                'substituteTeacher:id,name',
            ])
            ->get();

        return Inertia::render('Timetable/Substitutions', [
            'date' => $date->toDateString(),
            'today' => Carbon::today()->toDateString(),
            'teachers' => $teachers->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'absent' => $absentSet->has($t->id),
                'reason' => $absentSet->get($t->id)?->reason,
                'from_time' => $absentSet->get($t->id)?->from_time
                    ? substr($absentSet->get($t->id)->from_time, 0, 5)
                    : null,
            ]),
            'todaySlots' => $todaySlots->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'starts_at' => substr($s->starts_at, 0, 5),
            ])->values(),
            'assignments' => $assignments->map(fn ($a) => [
                'id' => $a->id,
                'time_slot' => $a->timetableEntry->timeSlot?->name,
                'time_range' => $a->timetableEntry->timeSlot
                    ? substr($a->timetableEntry->timeSlot->starts_at, 0, 5) . '–' . substr($a->timetableEntry->timeSlot->ends_at, 0, 5)
                    : null,
                'class' => $a->timetableEntry->schoolClass?->name,
                'section' => $a->timetableEntry->section?->name,
                'subject' => $a->timetableEntry->subject?->name,
                'original_teacher' => $a->originalTeacher?->name,
                'substitute_teacher' => $a->substituteTeacher?->name,
                'substitute_teacher_id' => $a->substitute_teacher_id,
                'status' => $a->status,
                'notes' => $a->notes,
            ]),
        ]);
    }

    /**
     * POST /timetable/substitutions/absences — toggle (or update) absence.
     *
     * Accepts an optional `from_time` (HH:MM) for partial-day absences
     * (e.g. teacher leaves after period 2). When present, only periods
     * starting at or after this time will be auto-substituted.
     *
     * Behaviour:
     *   - No row exists → create with the supplied from_time (or null = full day)
     *   - Row exists, action='update' → update from_time + reason in-place
     *     (lets admin convert "full-day absent" into "left after period 3"
     *     without first marking present then absent again)
     *   - Row exists, no action      → toggle (delete = mark present)
     */
    public function toggleAbsence(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:200'],
            'from_time' => ['nullable', 'date_format:H:i'],
            'action' => ['nullable', 'in:toggle,update'],
        ]);
        $action = $validated['action'] ?? 'toggle';

        $existing = TeacherAbsence::where('user_id', $validated['user_id'])
            ->where('absent_on', $validated['date'])
            ->first();

        if ($existing && $action === 'update') {
            // In-place update — most useful for "switch from full-day to partial-day"
            // (or vice versa) without losing the row.
            $existing->update([
                'reason' => $validated['reason'] ?? $existing->reason,
                'from_time' => $validated['from_time'] ?? null,
            ]);
            // Clear stale suggested covers — generate again to refresh.
            SubstitutionAssignment::where('date', $validated['date'])
                ->where('original_teacher_id', $validated['user_id'])
                ->where('status', 'suggested')
                ->delete();
            $msg = $validated['from_time']
                ? 'Updated — partial-day absence from ' . $validated['from_time'] . '.'
                : 'Updated — full-day absence.';
        } elseif ($existing) {
            $existing->delete();
            SubstitutionAssignment::where('date', $validated['date'])
                ->where('original_teacher_id', $validated['user_id'])
                ->where('status', 'suggested')
                ->delete();
            $msg = 'Marked present.';
        } else {
            TeacherAbsence::create([
                'user_id' => $validated['user_id'],
                'absent_on' => $validated['date'],
                'reason' => $validated['reason'] ?? null,
                'from_time' => $validated['from_time'] ?? null,
                'marked_by' => $user->id,
            ]);
            $msg = $validated['from_time']
                ? 'Marked absent from ' . $validated['from_time'] . '.'
                : 'Marked absent (full day).';
        }

        return redirect()->route('timetable.substitutions', ['date' => $validated['date']])
            ->with('success', $msg);
    }

    /** POST /timetable/substitutions/generate — run the algorithm. */
    public function generate(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);

        $validated = $request->validate(['date' => ['required', 'date']]);
        $date = Carbon::parse($validated['date']);
        $summary = $this->service->generateForDate($date, $user->id);

        $msg = sprintf(
            'Substitutions generated: %d covered, %d uncovered.',
            $summary['covered'],
            $summary['uncovered']
        );

        return redirect()->route('timetable.substitutions', ['date' => $date->toDateString()])
            ->with('success', $msg);
    }

    /** POST /timetable/substitutions/{assignment}/confirm */
    public function confirm(SubstitutionAssignment $assignment): RedirectResponse
    {
        $user = request()->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);

        $assignment->update(['status' => 'confirmed']);

        // Notify substitute that the assignment is now binding.
        $teacher = $assignment->substituteTeacher;
        if ($teacher) {
            $assignment->loadMissing(['timetableEntry.timeSlot', 'timetableEntry.subject', 'timetableEntry.schoolClass', 'timetableEntry.section', 'originalTeacher']);
            try {
                Notification::send($teacher, new SubstitutionAssignedNotification($assignment, 'confirmed'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->back()->with('success', 'Substitution confirmed.');
    }

    /** POST /timetable/substitutions/{assignment}/reassign — manual override. */
    public function reassign(Request $request, SubstitutionAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);

        $validated = $request->validate([
            'substitute_teacher_id' => ['required', 'exists:users,id'],
        ]);
        $previousId = $assignment->substitute_teacher_id;
        $assignment->update([
            'substitute_teacher_id' => $validated['substitute_teacher_id'],
            'status' => 'suggested',
        ]);

        // Only notify if substitute actually changed.
        if ($previousId !== $assignment->substitute_teacher_id) {
            $teacher = $assignment->fresh()->substituteTeacher;
            if ($teacher) {
                $assignment->loadMissing(['timetableEntry.timeSlot', 'timetableEntry.subject', 'timetableEntry.schoolClass', 'timetableEntry.section', 'originalTeacher']);
                try {
                    Notification::send($teacher, new SubstitutionAssignedNotification($assignment, 'reassigned'));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return redirect()->back()->with('success', 'Substitute reassigned.');
    }

    /** POST /timetable/substitutions/{assignment}/decline */
    public function decline(SubstitutionAssignment $assignment): RedirectResponse
    {
        $user = request()->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);

        $assignment->update(['status' => 'declined']);
        return redirect()->back()->with('success', 'Substitution declined.');
    }

    /** GET /timetable/substitutions/slip?date=YYYY-MM-DD — printable PDF. */
    public function slip(Request $request)
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        $assignments = SubstitutionAssignment::where('date', $date->toDateString())
            ->whereIn('status', ['suggested', 'confirmed'])
            ->with([
                'timetableEntry.timeSlot',
                'timetableEntry.subject',
                'timetableEntry.schoolClass',
                'timetableEntry.section',
                'originalTeacher:id,name',
                'substituteTeacher:id,name',
            ])
            ->get();

        // Group by substitute teacher so the slip is "who has to do what today".
        $bySub = $assignments->groupBy('substitute_teacher_id')->map(function ($rows, $uid) {
            return [
                'teacher_name' => $rows->first()->substituteTeacher?->name,
                'count' => $rows->count(),
                'rows' => $rows->map(fn ($a) => [
                    'time_slot' => $a->timetableEntry->timeSlot?->name,
                    'time_range' => $a->timetableEntry->timeSlot
                        ? substr($a->timetableEntry->timeSlot->starts_at, 0, 5) . '–' . substr($a->timetableEntry->timeSlot->ends_at, 0, 5)
                        : null,
                    'class' => $a->timetableEntry->schoolClass?->name,
                    'section' => $a->timetableEntry->section?->name,
                    'subject' => $a->timetableEntry->subject?->name,
                    'replaces' => $a->originalTeacher?->name,
                ]),
            ];
        })->values();

        $pdf = Pdf::loadView('reports.substitution-slip', [
            'date' => $date,
            'bySub' => $bySub,
            'totalAssignments' => $assignments->count(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("substitutions-{$date->toDateString()}.pdf");
    }
}
