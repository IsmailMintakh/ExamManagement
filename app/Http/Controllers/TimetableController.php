<?php

namespace App\Http\Controllers;

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

        $entries = TimetableEntry::where('section_id', $section->id)
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

        return Inertia::render('Timetable/Builder', [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'school_class_id' => $section->school_class_id,
                'class_name' => $section->schoolClass->name,
                'school_name' => $section->schoolClass->school->name,
            ],
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

        $validated = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*.weekday' => ['required', 'in:mon,tue,wed,thu,fri,sat'],
            'entries.*.time_slot_id' => ['required', 'exists:time_slots,id'],
            'entries.*.subject_id' => ['nullable', 'exists:subjects,id'],
            'entries.*.teacher_id' => ['nullable', 'exists:users,id'],
            'entries.*.room' => ['nullable', 'string', 'max:30'],
        ]);

        // Collision check: a teacher can't be assigned to two sections in
        // the same (weekday, time_slot). The unique key handles per-section
        // dupes; this catches per-teacher conflicts across sections.
        $conflicts = [];
        foreach ($validated['entries'] as $row) {
            if (!$row['teacher_id']) continue;
            $clash = TimetableEntry::where('teacher_id', $row['teacher_id'])
                ->where('weekday', $row['weekday'])
                ->where('time_slot_id', $row['time_slot_id'])
                ->where('section_id', '!=', $section->id)
                ->with('section.schoolClass')
                ->first();
            if ($clash) {
                $conflicts[] = sprintf(
                    '%s — %s %s with %s · %s already',
                    $row['weekday'], 'slot ' . $row['time_slot_id'],
                    'is taken by teacher',
                    $clash->section?->schoolClass?->name,
                    $clash->section?->name
                );
            }
        }
        if (!empty($conflicts)) {
            return redirect()->back()->withErrors([
                'entries' => 'Teacher conflict: ' . implode(' / ', array_slice($conflicts, 0, 3)),
            ]);
        }

        \DB::transaction(function () use ($validated, $section) {
            foreach ($validated['entries'] as $row) {
                TimetableEntry::updateOrCreate(
                    [
                        'section_id' => $section->id,
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

    /** GET /timetable/section/{section} — read-only class view. */
    public function sectionView(Section $section): Response
    {
        $section->load('schoolClass.school');
        $user = request()->user();
        if (!$user->isSuperAdmin() && $user->school_id !== $section->schoolClass->school_id) abort(403);

        $slots = TimeSlot::where('school_id', $section->schoolClass->school_id)->ordered()->get();
        $entries = TimetableEntry::where('section_id', $section->id)
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

        $entries = TimetableEntry::where('teacher_id', $user->id)
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
        $entries = TimetableEntry::where('section_id', $section->id)
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
        $entries = TimetableEntry::where('teacher_id', $user->id)
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

        $entries = TimetableEntry::query()
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

        $rawEntries = TimetableEntry::query()
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

        $pdf = Pdf::loadView('reports.timetable-routine', [
            'school' => $school,
            'periodSlots' => $slots,
            'allSlots' => $allSlots,
            'sections' => $sections,
            'consolidated' => $consolidated,
            'generatedAt' => now(),
            'session' => $session,
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

        $entries = TimetableEntry::query()
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

        $entriesBySection = TimetableEntry::query()
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
