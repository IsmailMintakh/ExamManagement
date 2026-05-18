<?php

namespace App\Http\Controllers;

use App\Models\SubstitutionAssignment;
use App\Models\TeacherAbsence;
use App\Models\TimetableEntry;
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
 * Class-Adjustment desk (daily absences + auto-cover).
 *
 * Marks who's out today, runs the engine to cover their empty periods, and
 * lets the admin confirm / reassign / decline each adjustment. Reassign and
 * confirm enforce the same hard constraints as the engine so a manual
 * override can't double-book a teacher.
 */
class SubstitutionController extends Controller
{
    public function __construct(protected SubstitutionService $service) {}

    /** The school an admin is acting within (super-admin = all schools). */
    protected function scopeSchoolId(User $user): ?int
    {
        return $user->isSuperAdmin() ? null : $user->school_id;
    }

    /** GET /timetable/adjustments?date=YYYY-MM-DD */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

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
            ->get(['user_id', 'reason', 'from_time', 'was_backdated']);
        $absentSet = $absences->keyBy('user_id');

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
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas(
                'timetableEntry.schoolClass',
                fn ($x) => $x->where('school_id', $user->school_id)
            ))
            ->get();

        return Inertia::render('Timetable/ClassAdjustments', [
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
                'was_backdated' => (bool) $absentSet->get($t->id)?->was_backdated,
            ]),
            'todaySlots' => $todaySlots->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'starts_at' => substr($s->starts_at, 0, 5),
            ])->values(),
            'assignments' => $assignments->map(fn ($a) => [
                'id' => $a->id,
                'time_slot' => $a->timetableEntry?->timeSlot?->name,
                'time_range' => $a->timetableEntry?->timeSlot
                    ? substr($a->timetableEntry->timeSlot->starts_at, 0, 5) . '–' . substr($a->timetableEntry->timeSlot->ends_at, 0, 5)
                    : null,
                'class' => $a->timetableEntry?->schoolClass?->name,
                'section' => $a->timetableEntry?->section?->name,
                'subject' => $a->timetableEntry?->subject?->name,
                'original_teacher' => $a->originalTeacher?->name,
                'substitute_teacher' => $a->substituteTeacher?->name,
                'substitute_teacher_id' => $a->substitute_teacher_id,
                'status' => $a->status,
                'notes' => $a->notes,
                'score_breakdown' => $a->score_breakdown,
            ]),
        ]);
    }

    /**
     * POST /timetable/adjustments/absences — toggle (or update) absence.
     * Supports full-day and partial-day ("left after period N") absences.
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
            $existing->update([
                'reason' => $validated['reason'] ?? $existing->reason,
                'from_time' => $validated['from_time'] ?? null,
            ]);
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
            $absentOn = Carbon::parse($validated['date'])->startOfDay();
            $wasBackdated = $absentOn->lt(now()->startOfDay());

            TeacherAbsence::create([
                'user_id' => $validated['user_id'],
                'academic_session_id' => \App\Models\AcademicSession::currentSession()?->id,
                'absent_on' => $validated['date'],
                'reason' => $validated['reason'] ?? null,
                'from_time' => $validated['from_time'] ?? null,
                'was_backdated' => $wasBackdated,
                'marked_by' => $user->id,
            ]);
            $msg = $validated['from_time']
                ? 'Marked absent from ' . $validated['from_time'] . '.'
                : 'Marked absent (full day).';
            if ($wasBackdated) $msg .= ' (back-dated entry)';
        }

        return redirect()->route('timetable.adjustments', ['date' => $validated['date']])
            ->with('success', $msg);
    }

    /** POST /timetable/adjustments/generate — run the engine. */
    public function generate(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);

        $validated = $request->validate(['date' => ['required', 'date']]);
        $date = Carbon::parse($validated['date']);

        $summary = $this->service->generateForDate($date, $user->id, $this->scopeSchoolId($user));

        $msg = sprintf(
            'Class adjustments generated: %d covered, %d uncovered.',
            $summary['covered'],
            $summary['uncovered']
        );

        return redirect()->route('timetable.adjustments', ['date' => $date->toDateString()])
            ->with($summary['uncovered'] > 0 ? 'warning' : 'success', $msg);
    }

    /** POST /timetable/adjustments/{assignment}/confirm */
    public function confirm(SubstitutionAssignment $assignment): RedirectResponse
    {
        $user = request()->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);
        $this->authorizeSchool($user, $assignment);

        if ($assignment->status === 'declined') {
            return redirect()->back()->withErrors([
                'adjustment' => 'This adjustment was declined — reassign a teacher before confirming.',
            ]);
        }

        // Guard: the substitute must not already be confirmed elsewhere in
        // the same period (double-booking).
        if ($conflict = $this->slotConflict($assignment, $assignment->substitute_teacher_id, ['confirmed'])) {
            return redirect()->back()->withErrors([
                'adjustment' => "Cannot confirm — {$assignment->substituteTeacher?->name} is already confirmed for {$conflict} in this period.",
            ]);
        }

        $assignment->update(['status' => 'confirmed']);

        $teacher = $assignment->substituteTeacher;
        if ($teacher) {
            $assignment->loadMissing(['timetableEntry.timeSlot', 'timetableEntry.subject', 'timetableEntry.schoolClass', 'timetableEntry.section', 'originalTeacher']);
            try {
                Notification::send($teacher, new SubstitutionAssignedNotification($assignment, 'confirmed'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->back()->with('success', 'Class adjustment confirmed.');
    }

    /** POST /timetable/adjustments/{assignment}/reassign — manual override. */
    public function reassign(Request $request, SubstitutionAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);
        $this->authorizeSchool($user, $assignment);

        $validated = $request->validate([
            'substitute_teacher_id' => ['required', 'exists:users,id'],
        ]);
        $newId = (int) $validated['substitute_teacher_id'];

        if ($error = $this->validateSubstitute($assignment, $newId)) {
            return redirect()->back()->withErrors(['adjustment' => $error]);
        }

        $previousId = $assignment->substitute_teacher_id;
        $assignment->update([
            'substitute_teacher_id' => $newId,
            'status' => 'suggested',
            'notes' => null,
        ]);

        if ($previousId !== $newId) {
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

        return redirect()->back()->with('success', 'Adjustment reassigned.');
    }

    /** POST /timetable/adjustments/{assignment}/decline */
    public function decline(SubstitutionAssignment $assignment): RedirectResponse
    {
        $user = request()->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);
        $this->authorizeSchool($user, $assignment);

        $assignment->update(['status' => 'declined']);
        return redirect()->back()->with('success', 'Adjustment declined. Re-generate or reassign to cover it.');
    }

    /** GET /timetable/adjustments/slip?date=YYYY-MM-DD — printable PDF. */
    public function slip(Request $request)
    {
        $user = $request->user();
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
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas(
                'timetableEntry.schoolClass',
                fn ($x) => $x->where('school_id', $user->school_id)
            ))
            ->get();

        $bySub = $assignments->groupBy('substitute_teacher_id')->map(function ($rows) {
            return [
                'teacher_name' => $rows->first()->substituteTeacher?->name,
                'count' => $rows->count(),
                'rows' => $rows->map(fn ($a) => [
                    'time_slot' => $a->timetableEntry?->timeSlot?->name,
                    'time_range' => $a->timetableEntry?->timeSlot
                        ? substr($a->timetableEntry->timeSlot->starts_at, 0, 5) . '–' . substr($a->timetableEntry->timeSlot->ends_at, 0, 5)
                        : null,
                    'class' => $a->timetableEntry?->schoolClass?->name,
                    'section' => $a->timetableEntry?->section?->name,
                    'subject' => $a->timetableEntry?->subject?->name,
                    'replaces' => $a->originalTeacher?->name,
                ]),
            ];
        })->values();

        $pdf = Pdf::loadView('reports.class-adjustment-slip', [
            'date' => $date,
            'bySub' => $bySub,
            'totalAssignments' => $assignments->count(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("class-adjustments-{$date->toDateString()}.pdf");
    }

    // ─────────────────────── helpers ───────────────────────

    /** School-admins may only touch adjustments inside their own school. */
    protected function authorizeSchool(User $user, SubstitutionAssignment $assignment): void
    {
        if ($user->isSuperAdmin()) return;
        $schoolId = $assignment->timetableEntry?->schoolClass?->school_id
            ?? $assignment->loadMissing('timetableEntry.schoolClass')->timetableEntry?->schoolClass?->school_id;
        if ($schoolId && $schoolId !== $user->school_id) abort(403);
    }

    /**
     * Validate a candidate substitute against the hard constraints. Returns
     * an error string, or null if the teacher may take this adjustment.
     */
    protected function validateSubstitute(SubstitutionAssignment $assignment, int $teacherId): ?string
    {
        $entry = $assignment->loadMissing('timetableEntry.timeSlot', 'timetableEntry.schoolClass')->timetableEntry;
        if (!$entry || !$entry->timeSlot) {
            return 'This period no longer exists in the timetable.';
        }

        $teacher = User::find($teacherId);
        if (!$teacher || !$teacher->is_active) {
            return 'Selected teacher is inactive.';
        }
        if ($teacherId === $assignment->original_teacher_id) {
            return 'The absent teacher cannot cover their own period.';
        }
        // Same school as the class.
        if ($teacher->school_id && $entry->schoolClass?->school_id
            && $teacher->school_id !== $entry->schoolClass->school_id) {
            return 'Teacher belongs to a different school.';
        }

        $date = Carbon::parse($assignment->date);
        $weekday = [1=>'mon',2=>'tue',3=>'wed',4=>'thu',5=>'fri',6=>'sat'][$date->dayOfWeekIso] ?? null;
        $slotStart = substr((string) $entry->timeSlot->starts_at, 0, 5);

        // Teacher's own absence covering this slot.
        $absence = TeacherAbsence::where('user_id', $teacherId)
            ->where('absent_on', $assignment->date)->first();
        if ($absence) {
            $from = $absence->from_time ? substr($absence->from_time, 0, 5) : null;
            if (!$from || $slotStart >= $from) {
                return 'Teacher is marked absent during this period.';
            }
        }

        // Teaching their own class this weekday + slot.
        $teaches = TimetableEntry::where('teacher_id', $teacherId)
            ->where('weekday', $weekday)
            ->where('time_slot_id', $entry->time_slot_id)
            ->exists();
        if ($teaches) {
            return 'Teacher is already teaching their own class in this period.';
        }

        // Already covering another class in this period (confirmed/suggested).
        if ($conflict = $this->slotConflict($assignment, $teacherId, ['confirmed', 'suggested'])) {
            return "Teacher is already assigned to {$conflict} in this period.";
        }

        // Daily cap.
        $coversToday = SubstitutionAssignment::where('date', $assignment->date)
            ->where('substitute_teacher_id', $teacherId)
            ->where('id', '!=', $assignment->id)
            ->whereIn('status', ['confirmed', 'suggested'])
            ->count();
        if ($coversToday >= SubstitutionService::MAX_COVERS_PER_DAY) {
            return 'Teacher is already at the daily adjustment limit (' . SubstitutionService::MAX_COVERS_PER_DAY . ').';
        }

        return null;
    }

    /**
     * Is $teacherId already assigned (in one of $statuses) to a DIFFERENT
     * period entry that shares this assignment's time slot on the same date?
     * Returns "Class · Section" of the clash, or null.
     */
    protected function slotConflict(SubstitutionAssignment $assignment, ?int $teacherId, array $statuses): ?string
    {
        if (!$teacherId) return null;
        $slotId = $assignment->loadMissing('timetableEntry')->timetableEntry?->time_slot_id;
        if (!$slotId) return null;

        $clash = SubstitutionAssignment::where('date', $assignment->date)
            ->where('substitute_teacher_id', $teacherId)
            ->where('id', '!=', $assignment->id)
            ->whereIn('status', $statuses)
            ->whereHas('timetableEntry', fn ($q) => $q->where('time_slot_id', $slotId))
            ->with('timetableEntry.schoolClass', 'timetableEntry.section')
            ->first();

        if (!$clash) return null;
        return trim(($clash->timetableEntry?->schoolClass?->name ?? '')
            . ' · ' . ($clash->timetableEntry?->section?->name ?? ''));
    }
}
