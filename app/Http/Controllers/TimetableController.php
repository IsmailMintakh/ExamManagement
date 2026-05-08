<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\TimetableEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Timetable hub. Three views:
 *   - setup       — bell-schedule (time slots) management
 *   - builder     — class+section grid where teacher+subject get assigned
 *   - viewer      — read-only printable views (per class, per teacher)
 */
class TimetableController extends Controller
{
    /** Resolves "which school am I working with" — super-admin can pick, others are pinned. */
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

    /** Helper: current academic session id, or null if none configured. */
    protected function sessionId(): ?int
    {
        return AcademicSession::currentSession()?->id;
    }

    /** GET /timetable — landing page: list classes, pick one to build/view. */
    public function index(Request $request): Response
    {
        $school = $this->resolveSchool($request);
        $user = $request->user();

        $classes = $school
            ? SchoolClass::where('school_id', $school->id)
                ->with(['sections' => fn ($q) => $q->active()])
                ->active()->ordered()->get()
            : collect();

        $hasSchedule = $school
            ? TimeSlot::where('school_id', $school->id)->exists()
            : false;

        $allSchools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : [];

        // ─── Stats ─── per-section completion + today's substitutions
        $sectionIds = $classes->flatMap->sections->pluck('id');
        $entryCounts = $sectionIds->isEmpty() ? collect() : TimetableEntry::query()
            ->forSession()
            ->whereIn('section_id', $sectionIds)
            ->whereNotNull('teacher_id')
            ->select('section_id', \DB::raw('COUNT(*) as cnt'))
            ->groupBy('section_id')
            ->pluck('cnt', 'section_id');

        $today = now();
        $weekdayMap = [1=>'mon',2=>'tue',3=>'wed',4=>'thu',5=>'fri',6=>'sat',7=>null];
        $todayCode = $weekdayMap[$today->dayOfWeekIso] ?? null;
        $todayAbsences = $school && $todayCode
            ? \App\Models\TeacherAbsence::query()
                ->whereDate('absent_on', $today->toDateString())
                ->whereHas('user', fn ($q) => $q->where('school_id', $school->id))
                ->count()
            : 0;
        $todayCovers = $school && $todayCode
            ? \App\Models\SubstitutionAssignment::query()
                ->whereDate('date', $today->toDateString())
                ->whereHas('timetableEntry.schoolClass', fn ($q) => $q->where('school_id', $school->id))
                ->count()
            : 0;

        $totalPeriodSlots = $school
            ? TimeSlot::where('school_id', $school->id)->where('type', 'period')->count()
            : 0;

        return Inertia::render('Timetable/Index', [
            'school' => $school ? ['id' => $school->id, 'name' => $school->name] : null,
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sections' => $c->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'entries_count' => (int) ($entryCounts[$s->id] ?? 0),
                ])->values(),
            ])->values(),
            'hasSchedule' => $hasSchedule,
            'allSchools' => $allSchools,
            'currentSchoolId' => $school?->id,
            'stats' => [
                'total_classes' => $classes->count(),
                'total_sections' => $sectionIds->count(),
                'total_period_slots' => $totalPeriodSlots,
                'today_absences' => $todayAbsences,
                'today_covers' => $todayCovers,
            ],
        ]);
    }

    /** GET /timetable/setup — bell-schedule editor. */
    public function setup(Request $request): Response
    {
        $school = $this->resolveSchool($request);
        abort_if(!$school, 404);

        $slots = TimeSlot::where('school_id', $school->id)->ordered()->get();

        return Inertia::render('Timetable/Setup', [
            'school' => ['id' => $school->id, 'name' => $school->name],
            'slots' => $slots,
        ]);
    }

    /** POST /timetable/setup — save the entire bell schedule (full replace). */
    public function saveSetup(Request $request): RedirectResponse
    {
        $school = $this->resolveSchool($request);
        abort_if(!$school, 404);

        $validated = $request->validate([
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.id' => ['nullable', 'integer'],
            'slots.*.name' => ['required', 'string', 'max:60'],
            'slots.*.type' => ['required', 'in:period,break,lunch,assembly'],
            'slots.*.starts_at' => ['required', 'date_format:H:i'],
            'slots.*.ends_at' => ['required', 'date_format:H:i', 'after:slots.*.starts_at'],
            'slots.*.weekdays' => ['required', 'array', 'min:1'],
            'slots.*.weekdays.*' => ['in:mon,tue,wed,thu,fri,sat'],
        ]);

        $kept = [];
        foreach ($validated['slots'] as $i => $slotData) {
            $payload = [
                'school_id' => $school->id,
                'name' => $slotData['name'],
                'type' => $slotData['type'],
                'starts_at' => $slotData['starts_at'],
                'ends_at' => $slotData['ends_at'],
                'sort_order' => $i,
                'weekdays' => $slotData['weekdays'],
            ];
            if (!empty($slotData['id'])) {
                $slot = TimeSlot::where('school_id', $school->id)->find($slotData['id']);
                if ($slot) {
                    $slot->update($payload);
                    $kept[] = $slot->id;
                    continue;
                }
            }
            $created = TimeSlot::create($payload);
            $kept[] = $created->id;
        }
        // Delete any slots no longer in the submitted list.
        TimeSlot::where('school_id', $school->id)->whereNotIn('id', $kept)->delete();

        return redirect()->route('timetable.index', ['school_id' => $school->id])
            ->with('success', 'Bell schedule saved.');
    }

    /** GET /timetable/builder/{section} — grid editor for one section. */
    public function builder(Request $request, Section $section): Response
    {
        $section->load('schoolClass.school');
        $schoolId = $section->schoolClass->school_id;
        $user = $request->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $schoolId) abort(403);

        $slots = TimeSlot::where('school_id', $schoolId)->ordered()->get();
        if ($slots->isEmpty()) {
            return Inertia::render('Timetable/EmptySchedule', [
                'school' => ['id' => $schoolId, 'name' => $section->schoolClass->school->name],
            ]);
        }

        $entries = TimetableEntry::forSession()
            ->where('section_id', $section->id)
            ->with(['subject:id,name,code', 'teacher:id,name'])
            ->get();

        // Subjects assigned to this class via the class_subjects pivot.
        // Qualify columns explicitly — belongsToMany joins class_subjects so
        // bare `id`/`name` would be ambiguous between the two tables.
        $subjects = $section->schoolClass->subjects()
            ->where('subjects.is_active', true)
            ->orderBy('subjects.name')
            ->get(['subjects.id', 'subjects.name', 'subjects.code']);

        // Teachers in this school — class-teachers + subject-teachers.
        $teachers = User::where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher', 'school-admin']))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Other sections (for "copy from" picker) — must have entries to copy.
        $copyCandidates = Section::query()
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $schoolId))
            ->where('id', '!=', $section->id)
            ->whereHas('timetableEntries', fn ($q) => $q->forSession())
            ->with('schoolClass:id,name')
            ->active()
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'class_name' => $s->schoolClass?->name,
                'name' => $s->name,
            ]);

        return Inertia::render('Timetable/Builder', [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'school_class_id' => $section->school_class_id,
                'class_name' => $section->schoolClass->name,
                'school_name' => $section->schoolClass->school->name,
                'timetable_locked' => (bool) $section->timetable_locked,
                'timetable_locked_at' => $section->timetable_locked_at?->format('d M Y, h:i A'),
                'timetable_locked_by_name' => $section->timetableLockedBy?->name,
            ],
            'copyCandidates' => $copyCandidates,
            'slots' => $slots,
            'entries' => $entries->map(fn ($e) => [
                'id' => $e->id,
                'weekday' => $e->weekday,
                'time_slot_id' => $e->time_slot_id,
                'subject_id' => $e->subject_id,
                'teacher_id' => $e->teacher_id,
                'room' => $e->room,
                'subject_name' => $e->subject?->name,
                'teacher_name' => $e->teacher?->name,
            ]),
            'subjects' => $subjects,
            'teachers' => $teachers,
        ]);
    }

    /** POST /timetable/builder/{section} — bulk save grid. */
    public function saveBuilder(Request $request, Section $section): RedirectResponse
    {
        $section->load('schoolClass');
        $user = $request->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $section->schoolClass->school_id) abort(403);

        // Locked timetables can only be edited after explicit unlock.
        if ($section->timetable_locked) {
            return redirect()->back()->withErrors([
                'entries' => 'This section\'s timetable is locked. Unlock it first to make changes.',
            ]);
        }

        $validated = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*.weekday' => ['required', 'in:mon,tue,wed,thu,fri,sat'],
            'entries.*.time_slot_id' => ['required', 'exists:time_slots,id'],
            'entries.*.subject_id' => ['nullable', 'exists:subjects,id'],
            'entries.*.teacher_id' => ['nullable', 'exists:users,id'],
            'entries.*.room' => ['nullable', 'string', 'max:30'],
        ]);

        $sessionId = $this->sessionId();

        // ── Conflict checks: teacher (cross-section) + room (cross-section) ──
        $conflicts = [];
        $roomConflicts = [];
        foreach ($validated['entries'] as $row) {
            if ($row['teacher_id']) {
                $clash = TimetableEntry::forSession($sessionId)
                    ->where('teacher_id', $row['teacher_id'])
                    ->where('weekday', $row['weekday'])
                    ->where('time_slot_id', $row['time_slot_id'])
                    ->where('section_id', '!=', $section->id)
                    ->with('section.schoolClass')
                    ->first();
                if ($clash) {
                    $conflicts[] = sprintf(
                        '%s slot — teacher already assigned to %s · %s',
                        $row['weekday'],
                        $clash->section?->schoolClass?->name,
                        $clash->section?->name
                    );
                }
            }
            if (!empty($row['room'])) {
                $roomClash = TimetableEntry::forSession($sessionId)
                    ->where('room', $row['room'])
                    ->where('weekday', $row['weekday'])
                    ->where('time_slot_id', $row['time_slot_id'])
                    ->where('section_id', '!=', $section->id)
                    ->with('section.schoolClass')
                    ->first();
                if ($roomClash) {
                    $roomConflicts[] = sprintf(
                        'Room %s — already in use by %s · %s',
                        $row['room'],
                        $roomClash->section?->schoolClass?->name,
                        $roomClash->section?->name
                    );
                }
            }
        }
        if (!empty($conflicts)) {
            return redirect()->back()->withErrors([
                'entries' => 'Teacher conflict: ' . implode(' / ', array_slice($conflicts, 0, 3)),
            ]);
        }
        if (!empty($roomConflicts)) {
            return redirect()->back()->withErrors([
                'entries' => 'Room conflict: ' . implode(' / ', array_slice($roomConflicts, 0, 3)),
            ]);
        }

        \DB::transaction(function () use ($validated, $section, $sessionId) {
            foreach ($validated['entries'] as $row) {
                TimetableEntry::updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'academic_session_id' => $sessionId,
                        'weekday' => $row['weekday'],
                        'time_slot_id' => $row['time_slot_id'],
                    ],
                    [
                        'school_class_id' => $section->school_class_id,
                        'subject_id' => $row['subject_id'] ?: null,
                        'teacher_id' => $row['teacher_id'] ?: null,
                        'room' => $row['room'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('timetable.section', $section->id)
            ->with('success', 'Timetable saved.');
    }

    /**
     * POST /timetable/section/{section}/lock — lock the timetable.
     * Locked sections refuse builder edits until unlocked.
     */
    public function lockSection(Request $request, Section $section): RedirectResponse
    {
        $section->load('schoolClass');
        $user = $request->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $section->schoolClass->school_id) abort(403);

        $section->update([
            'timetable_locked' => true,
            'timetable_locked_at' => now(),
            'timetable_locked_by' => $user->id,
        ]);

        return redirect()->back()->with('success', 'Timetable locked. Edits blocked until unlocked.');
    }

    /** POST /timetable/section/{section}/unlock */
    public function unlockSection(Request $request, Section $section): RedirectResponse
    {
        $section->load('schoolClass');
        $user = $request->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $section->schoolClass->school_id) abort(403);

        $section->update([
            'timetable_locked' => false,
            'timetable_locked_at' => null,
            'timetable_locked_by' => null,
        ]);

        return redirect()->back()->with('success', 'Timetable unlocked. You can edit again.');
    }

    /**
     * POST /timetable/section/{target}/copy-from/{source}
     *
     * Bulk-copies all entries from $source into $target, replacing whatever
     * was there. Useful when V-A is done and you want V-B to start with the
     * same routine before tweaking.
     *
     * Refuses if target is locked. Cross-section teacher conflicts are NOT
     * checked by copy — admin sees them after the copy and can fix from the
     * builder (which does enforce them on the next save).
     */
    public function copyFrom(Request $request, Section $target, Section $source): RedirectResponse
    {
        $target->load('schoolClass');
        $source->load('schoolClass');
        $user = $request->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $target->schoolClass->school_id) abort(403);
        if (!$user->isSuperAdmin() && $user->school_id !== $source->schoolClass->school_id) abort(403);

        if ($target->timetable_locked) {
            return redirect()->back()->withErrors([
                'copy' => 'Target section is locked. Unlock it first.',
            ]);
        }

        $sessionId = $this->sessionId();
        $sourceEntries = TimetableEntry::forSession($sessionId)
            ->where('section_id', $source->id)
            ->get();

        if ($sourceEntries->isEmpty()) {
            return redirect()->back()->withErrors([
                'copy' => 'Source section has no timetable to copy.',
            ]);
        }

        \DB::transaction(function () use ($sourceEntries, $target, $sessionId) {
            // Wipe target entries first to avoid stale ones lingering.
            TimetableEntry::forSession($sessionId)
                ->where('section_id', $target->id)
                ->delete();

            foreach ($sourceEntries as $e) {
                TimetableEntry::create([
                    'section_id' => $target->id,
                    'school_class_id' => $target->school_class_id,
                    'academic_session_id' => $sessionId,
                    'weekday' => $e->weekday,
                    'time_slot_id' => $e->time_slot_id,
                    'subject_id' => $e->subject_id,
                    // Don't copy the teacher — same teacher in two sections at the
                    // same time would conflict. Admin reassigns per cell.
                    'teacher_id' => null,
                    'room' => $e->room,
                ]);
            }
        });

        return redirect()->route('timetable.builder', $target->id)
            ->with('success', "Copied {$sourceEntries->count()} entries from {$source->schoolClass->name} {$source->name}. Now assign teachers.");
    }

    /**
     * POST /timetable/swap — swap two cells' (subject, teacher) values.
     *
     * Body: { from_entry_id, to_entry_id } — both must be in the same school
     * and the same session. Either side can be empty (then "swap" effectively
     * moves the assignment from one cell to the other).
     */
    public function swap(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) abort(403);

        $validated = $request->validate([
            'from_entry_id' => ['required', 'exists:timetable_entries,id'],
            'to_entry_id' => ['required', 'exists:timetable_entries,id', 'different:from_entry_id'],
        ]);

        $a = TimetableEntry::with('section.schoolClass')->find($validated['from_entry_id']);
        $b = TimetableEntry::with('section.schoolClass')->find($validated['to_entry_id']);

        // Same school required
        $aSchool = $a->section?->schoolClass?->school_id;
        $bSchool = $b->section?->schoolClass?->school_id;
        if ($aSchool !== $bSchool) {
            return redirect()->back()->withErrors(['swap' => 'Both cells must belong to the same school.']);
        }
        if (!$user->isSuperAdmin() && $user->school_id !== $aSchool) abort(403);

        if ($a->section?->timetable_locked || $b->section?->timetable_locked) {
            return redirect()->back()->withErrors(['swap' => 'One of the sections is locked.']);
        }

        \DB::transaction(function () use ($a, $b) {
            [$aSubj, $aTeach, $aRoom] = [$a->subject_id, $a->teacher_id, $a->room];
            $a->update(['subject_id' => $b->subject_id, 'teacher_id' => $b->teacher_id, 'room' => $b->room]);
            $b->update(['subject_id' => $aSubj, 'teacher_id' => $aTeach, 'room' => $aRoom]);
        });

        return redirect()->back()->with('success', 'Cells swapped.');
    }

    /**
     * POST /timetable/setup/preset — apply a bell-schedule preset.
     *
     * Replaces the school's TimeSlots with one of the canned templates:
     *   - winter   : 8 periods, breaks at 10:00 and 12:00, ends 13:30
     *   - summer   : 8 periods starting 07:30, ends 12:30
     *   - ramadan  : 6 shorter periods, ends 12:00
     */
    public function applyPreset(Request $request): RedirectResponse
    {
        $school = $this->resolveSchool($request);
        abort_if(!$school, 404);

        $validated = $request->validate([
            'preset' => ['required', 'in:winter,summer,ramadan'],
        ]);

        $presets = $this->bellSchedulePresets();
        $preset = $presets[$validated['preset']] ?? null;
        abort_if(!$preset, 422);

        \DB::transaction(function () use ($school, $preset) {
            TimeSlot::where('school_id', $school->id)->delete();
            foreach ($preset['slots'] as $i => $slot) {
                TimeSlot::create([
                    'school_id' => $school->id,
                    'name' => $slot['name'],
                    'type' => $slot['type'],
                    'starts_at' => $slot['starts_at'],
                    'ends_at' => $slot['ends_at'],
                    'sort_order' => $i,
                    'weekdays' => $slot['weekdays'],
                ]);
            }
        });

        return redirect()->route('timetable.setup', ['school_id' => $school->id])
            ->with('success', 'Applied "' . $preset['label'] . '" bell schedule preset. Tweak as needed.');
    }

    /** Bell-schedule preset library. */
    protected function bellSchedulePresets(): array
    {
        $allDays = ['mon','tue','wed','thu','fri','sat'];
        $exceptFri = ['mon','tue','wed','thu','sat'];

        return [
            'winter' => [
                'label' => 'Winter (08:00 start, 13:30 end)',
                'slots' => [
                    ['name' => 'Period 1', 'type' => 'period', 'starts_at' => '08:00', 'ends_at' => '08:40', 'weekdays' => $allDays],
                    ['name' => 'Period 2', 'type' => 'period', 'starts_at' => '08:40', 'ends_at' => '09:20', 'weekdays' => $allDays],
                    ['name' => 'Period 3', 'type' => 'period', 'starts_at' => '09:20', 'ends_at' => '10:00', 'weekdays' => $allDays],
                    ['name' => 'Break',    'type' => 'break',  'starts_at' => '10:00', 'ends_at' => '10:20', 'weekdays' => $allDays],
                    ['name' => 'Period 4', 'type' => 'period', 'starts_at' => '10:20', 'ends_at' => '11:00', 'weekdays' => $allDays],
                    ['name' => 'Period 5', 'type' => 'period', 'starts_at' => '11:00', 'ends_at' => '11:40', 'weekdays' => $exceptFri],
                    ['name' => 'Lunch',    'type' => 'lunch',  'starts_at' => '11:40', 'ends_at' => '12:10', 'weekdays' => $exceptFri],
                    ['name' => 'Period 6', 'type' => 'period', 'starts_at' => '12:10', 'ends_at' => '12:50', 'weekdays' => $exceptFri],
                    ['name' => 'Period 7', 'type' => 'period', 'starts_at' => '12:50', 'ends_at' => '13:30', 'weekdays' => $exceptFri],
                ],
            ],
            'summer' => [
                'label' => 'Summer (07:30 start, 12:30 end)',
                'slots' => [
                    ['name' => 'Period 1', 'type' => 'period', 'starts_at' => '07:30', 'ends_at' => '08:05', 'weekdays' => $allDays],
                    ['name' => 'Period 2', 'type' => 'period', 'starts_at' => '08:05', 'ends_at' => '08:40', 'weekdays' => $allDays],
                    ['name' => 'Period 3', 'type' => 'period', 'starts_at' => '08:40', 'ends_at' => '09:15', 'weekdays' => $allDays],
                    ['name' => 'Break',    'type' => 'break',  'starts_at' => '09:15', 'ends_at' => '09:35', 'weekdays' => $allDays],
                    ['name' => 'Period 4', 'type' => 'period', 'starts_at' => '09:35', 'ends_at' => '10:10', 'weekdays' => $allDays],
                    ['name' => 'Period 5', 'type' => 'period', 'starts_at' => '10:10', 'ends_at' => '10:45', 'weekdays' => $allDays],
                    ['name' => 'Period 6', 'type' => 'period', 'starts_at' => '10:45', 'ends_at' => '11:20', 'weekdays' => $exceptFri],
                    ['name' => 'Lunch',    'type' => 'lunch',  'starts_at' => '11:20', 'ends_at' => '11:40', 'weekdays' => $exceptFri],
                    ['name' => 'Period 7', 'type' => 'period', 'starts_at' => '11:40', 'ends_at' => '12:15', 'weekdays' => $exceptFri],
                    ['name' => 'Period 8', 'type' => 'period', 'starts_at' => '12:15', 'ends_at' => '12:50', 'weekdays' => $exceptFri],
                ],
            ],
            'ramadan' => [
                'label' => 'Ramadan (08:00 start, 12:00 end, no lunch)',
                'slots' => [
                    ['name' => 'Period 1', 'type' => 'period', 'starts_at' => '08:00', 'ends_at' => '08:35', 'weekdays' => $allDays],
                    ['name' => 'Period 2', 'type' => 'period', 'starts_at' => '08:35', 'ends_at' => '09:10', 'weekdays' => $allDays],
                    ['name' => 'Period 3', 'type' => 'period', 'starts_at' => '09:10', 'ends_at' => '09:45', 'weekdays' => $allDays],
                    ['name' => 'Break',    'type' => 'break',  'starts_at' => '09:45', 'ends_at' => '10:00', 'weekdays' => $allDays],
                    ['name' => 'Period 4', 'type' => 'period', 'starts_at' => '10:00', 'ends_at' => '10:35', 'weekdays' => $allDays],
                    ['name' => 'Period 5', 'type' => 'period', 'starts_at' => '10:35', 'ends_at' => '11:10', 'weekdays' => $allDays],
                    ['name' => 'Period 6', 'type' => 'period', 'starts_at' => '11:10', 'ends_at' => '11:45', 'weekdays' => $exceptFri],
                ],
            ],
        ];
    }

    /** GET /timetable/section/{section} — read-only class view. */
    public function sectionView(Section $section): Response
    {
        $section->load('schoolClass.school');
        $user = request()->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $section->schoolClass->school_id) abort(403);

        $slots = TimeSlot::where('school_id', $section->schoolClass->school_id)->ordered()->get();
        $entries = TimetableEntry::forSession()
            ->where('section_id', $section->id)
            ->with(['subject:id,name,code', 'teacher:id,name'])
            ->get()
            ->keyBy(fn ($e) => $e->weekday . '|' . $e->time_slot_id);

        return Inertia::render('Timetable/SectionView', [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'class_name' => $section->schoolClass->name,
                'school_name' => $section->schoolClass->school->name,
            ],
            'slots' => $slots,
            'entries' => $entries,
        ]);
    }

    /** GET /timetable/teacher/{user} — teacher view. */
    public function teacherView(User $user): Response
    {
        $actor = request()->user();
        // Only admins or the teacher themselves can see this.
        if ($actor->id !== $user->id && !$actor->isSuperAdmin() && !$actor->isSchoolAdmin()) abort(403);
        if ($actor->isSchoolAdmin() && $actor->school_id !== $user->school_id) abort(403);

        $slots = $user->school_id
            ? TimeSlot::where('school_id', $user->school_id)->ordered()->get()
            : collect();

        $entries = TimetableEntry::forSession()
            ->where('teacher_id', $user->id)
            ->with(['subject:id,name,code', 'schoolClass:id,name', 'section:id,name'])
            ->get()
            ->keyBy(fn ($e) => $e->weekday . '|' . $e->time_slot_id);

        return Inertia::render('Timetable/TeacherView', [
            'teacher' => ['id' => $user->id, 'name' => $user->name],
            'school' => $user->school ? ['id' => $user->school->id, 'name' => $user->school->name] : null,
            'slots' => $slots,
            'entries' => $entries,
        ]);
    }

    /** GET /timetable/section/{section}/pdf — printable class PDF. */
    public function sectionPdf(Section $section)
    {
        $section->load('schoolClass.school');
        $slots = TimeSlot::where('school_id', $section->schoolClass->school_id)->ordered()->get();
        $entries = TimetableEntry::forSession()
            ->where('section_id', $section->id)
            ->with(['subject:id,name,code', 'teacher:id,name'])
            ->get()
            ->keyBy(fn ($e) => $e->weekday . '|' . $e->time_slot_id);

        $pdf = Pdf::loadView('reports.timetable-class', [
            'section' => $section,
            'slots' => $slots,
            'entries' => $entries,
            'school' => $section->schoolClass->school,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("timetable-{$section->schoolClass->slug}-{$section->slug}.pdf");
    }

    /** GET /timetable/teacher/{user}/pdf — printable teacher PDF. */
    public function teacherPdf(User $user)
    {
        $slots = $user->school_id
            ? TimeSlot::where('school_id', $user->school_id)->ordered()->get()
            : collect();
        $entries = TimetableEntry::forSession()
            ->where('teacher_id', $user->id)
            ->with(['subject:id,name,code', 'schoolClass:id,name', 'section:id,name'])
            ->get()
            ->keyBy(fn ($e) => $e->weekday . '|' . $e->time_slot_id);

        $pdf = Pdf::loadView('reports.timetable-teacher', [
            'teacher' => $user,
            'slots' => $slots,
            'entries' => $entries,
            'school' => $user->school,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("timetable-teacher-{$user->id}.pdf");
    }

    /**
     * GET /timetable/master — interactive single-screen "all classes ×
     * sections at once" view. Day-by-day grid with rows = time slots,
     * columns = (class, section), cells = subject + teacher.
     *
     * The principal's "wall view" — see every class's Monday in one shot,
     * spot conflicts, double-bookings, and gaps.
     */
    public function master(Request $request): Response
    {
        $school = $this->resolveSchool($request);
        abort_if(!$school, 404);
        $user = $request->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $school->id) abort(403);

        $slots = TimeSlot::where('school_id', $school->id)->ordered()->get();
        $sections = Section::query()
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $school->id))
            ->with('schoolClass:id,name,sort_order')
            ->active()
            ->get()
            ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->sort_order ?? 999, $s->name))
            ->values();

        $entries = TimetableEntry::forSession()
            ->whereIn('section_id', $sections->pluck('id'))
            ->with(['subject:id,name,code', 'teacher:id,name'])
            ->get()
            // Key: "<weekday>|<slotId>|<sectionId>" → entry, for O(1) lookup in the grid.
            ->keyBy(fn ($e) => $e->weekday . '|' . $e->time_slot_id . '|' . $e->section_id);

        $allSchools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : [];

        return Inertia::render('Timetable/Master', [
            'school' => ['id' => $school->id, 'name' => $school->name],
            'slots' => $slots,
            'sections' => $sections->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'class_name' => $s->schoolClass?->name,
                'school_class_id' => $s->school_class_id,
            ])->values(),
            'entries' => $entries,
            'allSchools' => $allSchools,
            'currentSchoolId' => $school->id,
        ]);
    }

    /**
     * GET /timetable/routine/pdf — the compact "school routine" wall chart.
     * One row per section, columns grouped by day → period. Designed to fit
     * the entire week on 1-2 A4 landscape sheets. Suitable for posting in
     * the staff room and reprinting whenever the timetable changes.
     */
    public function routinePdf(Request $request)
    {
        $school = $this->resolveSchool($request);
        abort_if(!$school, 404);
        $user = $request->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $school->id) abort(403);

        $slots = TimeSlot::where('school_id', $school->id)
            ->where('type', 'period')
            ->orderBy('starts_at')
            ->get();
        abort_if($slots->isEmpty(), 404, 'Bell schedule not configured.');

        $allSlots = TimeSlot::where('school_id', $school->id)->ordered()->get();

        $sections = Section::query()
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $school->id))
            ->with('schoolClass:id,name,sort_order')
            ->active()
            ->get()
            ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->sort_order ?? 999, $s->name))
            ->values();

        $rawEntries = TimetableEntry::forSession()
            ->whereIn('section_id', $sections->pluck('id'))
            ->with(['subject:id,name,code', 'teacher:id,name'])
            ->get();

        // ─── Consolidate entries by (section, slot), ignoring weekday ───
        // The routine is meant for schools where the daily timetable is
        // (essentially) the same Mon–Sat. For each (section, slot), we pick
        // the most common (subject, teacher) tuple and flag if there's any
        // weekday variance so the admin can spot the exception cell.
        $consolidated = $rawEntries
            ->groupBy(fn ($e) => $e->section_id . '|' . $e->time_slot_id)
            ->map(function ($rows) {
                // Tally each (subject_id, teacher_id) signature.
                $tally = [];
                foreach ($rows as $r) {
                    $sig = ($r->subject_id ?? '0') . ':' . ($r->teacher_id ?? '0');
                    if (!isset($tally[$sig])) {
                        $tally[$sig] = ['entry' => $r, 'days' => [], 'count' => 0];
                    }
                    $tally[$sig]['days'][] = $r->weekday;
                    $tally[$sig]['count']++;
                }
                // Sort by count desc — dominant assignment wins.
                uasort($tally, fn ($a, $b) => $b['count'] <=> $a['count']);
                $dominant = reset($tally);
                $variant = count($tally) > 1; // there's at least one different day
                return [
                    'entry' => $dominant['entry'],
                    'days_covered' => $dominant['days'],
                    'has_variant' => $variant,
                    'variants' => array_values($tally),
                ];
            });

        $session = \App\Models\AcademicSession::currentSession();
        $show = $request->input('show', 'both'); // subject | teacher | both
        if (!in_array($show, ['subject', 'teacher', 'both'], true)) $show = 'both';

        $pdf = Pdf::loadView('reports.timetable-routine', [
            'school' => $school,
            'periodSlots' => $slots,
            'allSlots' => $allSlots,
            'sections' => $sections,
            'consolidated' => $consolidated,
            'generatedAt' => now(),
            'session' => $session,
            'show' => $show,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("school-routine-{$school->id}.pdf");
    }

    /**
     * GET /timetable/master/pdf — same matrix, one A4-landscape page per
     * weekday. Typical use: print 6 sheets, pin them in the staff room.
     */
    public function masterPdf(Request $request)
    {
        $school = $this->resolveSchool($request);
        abort_if(!$school, 404);
        $user = $request->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $school->id) abort(403);

        $slots = TimeSlot::where('school_id', $school->id)->ordered()->get();
        abort_if($slots->isEmpty(), 404, 'Bell schedule not configured.');

        $sections = Section::query()
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $school->id))
            ->with('schoolClass:id,name,sort_order')
            ->active()
            ->get()
            ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->sort_order ?? 999, $s->name))
            ->values();

        $entries = TimetableEntry::forSession()
            ->whereIn('section_id', $sections->pluck('id'))
            ->with(['subject:id,name,code', 'teacher:id,name'])
            ->get()
            ->keyBy(fn ($e) => $e->weekday . '|' . $e->time_slot_id . '|' . $e->section_id);

        $pdf = Pdf::loadView('reports.timetable-master', [
            'school' => $school,
            'slots' => $slots,
            'sections' => $sections,
            'entries' => $entries,
        ])->setPaper('a3', 'landscape');

        return $pdf->stream("master-timetable-school-{$school->id}.pdf");
    }

    /** GET /timetable/school/pdf — bundled all-sections PDF for one school. */
    public function schoolPdf(Request $request)
    {
        $school = $this->resolveSchool($request);
        abort_if(!$school, 404);
        $user = $request->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $school->id) abort(403);

        $slots = TimeSlot::where('school_id', $school->id)->ordered()->get();
        abort_if($slots->isEmpty(), 404, 'Bell schedule not configured.');

        $sections = Section::query()
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $school->id))
            ->with(['schoolClass:id,name,sort_order'])
            ->active()
            ->orderBy(SchoolClass::select('sort_order')->whereColumn('school_classes.id', 'sections.school_class_id'))
            ->orderBy('name')
            ->get();

        $entriesBySection = TimetableEntry::forSession()
            ->whereIn('section_id', $sections->pluck('id'))
            ->with(['subject:id,name,code', 'teacher:id,name'])
            ->get()
            ->groupBy('section_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($e) => $e->weekday . '|' . $e->time_slot_id));

        $pdf = Pdf::loadView('reports.timetable-school', [
            'school' => $school,
            'sections' => $sections,
            'slots' => $slots,
            'entriesBySection' => $entriesBySection,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("timetable-school-{$school->id}.pdf");
    }
}
