<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamInvigilator;
use App\Models\ExamRoom;
use App\Models\ExamSchedule;
use App\Models\ExamSeat;
use App\Models\ExamSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExamSchedulingController extends Controller
{
    // Note: auth checks are applied at the route level (see routes/web.php
    // → 'scheduling' group). Read routes require `scheduling.view`, write
    // routes (storeSchedule, storeRoom, autoAssignSeats, clearSeats,
    // storeInvigilator, deleteInvigilator) require `scheduling.manage`.
    // Laravel 11+ removed $this->middleware() from controllers — apply
    // middleware in routes instead.

    /**
     * Top-level list of exams available for scheduling. Entry point for the module.
     */
    public function examsList(Request $request): Response
    {
        $user = $request->user();
        $schoolId = $user->isSuperAdmin() ? null : $user->school_id;

        $exams = Exam::query()
            ->whereIn('status', ['draft', 'published', 'marks_entry', 'processing', 'completed'])
            ->when($schoolId, fn ($q) => $q->visibleToSchool($schoolId))
            ->with(['examType', 'academicSession'])
            ->withCount(['examSubjects'])
            ->latest()
            ->get()
            ->map(function ($exam) {
                $schedulesCount = ExamSchedule::where('exam_id', $exam->id)->count();
                $seatsCount = ExamSeat::where('exam_id', $exam->id)->count();
                $invigilatorsCount = ExamInvigilator::where('exam_id', $exam->id)->count();

                return [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'exam_type' => $exam->examType?->name,
                    'session' => $exam->academicSession?->name,
                    'status' => $exam->status,
                    'start_date' => $exam->start_date?->format('d M Y'),
                    'end_date' => $exam->end_date?->format('d M Y'),
                    'exam_subjects_count' => $exam->exam_subjects_count,
                    'schedules_count' => $schedulesCount,
                    'seats_count' => $seatsCount,
                    'invigilators_count' => $invigilatorsCount,
                    'is_fully_scheduled' => $exam->exam_subjects_count > 0 && $schedulesCount >= $exam->exam_subjects_count,
                ];
            });

        $roomsCount = ExamRoom::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->count();

        return Inertia::render('Scheduling/Exams', [
            'exams' => $exams,
            'roomsCount' => $roomsCount,
        ]);
    }

    /**
     * Main scheduling hub for an exam — shows 4 feature cards with counts.
     */
    public function index(Exam $exam): Response
    {
        $user = request()->user();
        $schoolId = $user->isSuperAdmin() ? null : $user->school_id;

        $exam->load(['examType', 'academicSession']);

        // Counts of exam_subject rows for this exam (the universe we need to schedule)
        $examSubjectsCount = ExamSubject::where('exam_id', $exam->id)->count();
        $schedulesCount = ExamSchedule::where('exam_id', $exam->id)->count();
        $seatsCount = ExamSeat::where('exam_id', $exam->id)->count();
        $invigilatorsCount = ExamInvigilator::where('exam_id', $exam->id)->count();

        $roomsCount = ExamRoom::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->count();

        // Count students in this exam's applicable classes/schools
        $studentsCount = Student::query()
            ->where('status', 'active')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->count();

        $stats = [
            'exam_subjects' => $examSubjectsCount,
            'schedules' => $schedulesCount,
            'seats' => $seatsCount,
            'invigilators' => $invigilatorsCount,
            'rooms' => $roomsCount,
            'students' => $studentsCount,
        ];

        return Inertia::render('Scheduling/Index', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'status' => $exam->status,
                'exam_type' => $exam->examType?->name,
                'session' => $exam->academicSession?->name,
                'start_date' => $exam->start_date?->toDateString(),
                'end_date' => $exam->end_date?->toDateString(),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Date sheet management — shows examSubjects with their schedule row (if exists).
     */
    public function datesheet(Exam $exam): Response
    {
        $exam->load(['examType', 'academicSession']);

        $examSubjects = ExamSubject::where('exam_id', $exam->id)
            ->with(['subject', 'schoolClass'])
            ->orderBy('school_class_id')
            ->orderBy('subject_id')
            ->get();

        // ── Hide (subject, class) pairs not in the class curriculum ──
        // Same logic as Exam Show: an exam can carry orphan rows like
        // "English → Nursery" which the class isn't actually taught.
        // Filter them out so each class shows only its real subjects.
        $classIds = $examSubjects->pluck('school_class_id')->unique();
        $curriculum = \DB::table('class_subjects')
            ->where('is_active', true)
            ->whereIn('school_class_id', $classIds)
            ->get(['school_class_id', 'subject_id'])
            ->map(fn ($r) => $r->school_class_id.':'.$r->subject_id)
            ->flip()
            ->toArray();

        $examSubjects = $examSubjects
            ->filter(fn ($es) => isset($curriculum[$es->school_class_id.':'.$es->subject_id]))
            ->values();

        $schedules = ExamSchedule::where('exam_id', $exam->id)
            ->get()
            ->keyBy(fn ($s) => $s->subject_id . '-' . $s->school_class_id);

        $rows = $examSubjects->map(function ($es) use ($schedules) {
            $key = $es->subject_id . '-' . $es->school_class_id;
            $sch = $schedules->get($key);
            return [
                'id' => $es->id,
                'subject_id' => $es->subject_id,
                'school_class_id' => $es->school_class_id,
                'subject_name' => $es->subject?->name,
                'subject_code' => $es->subject?->code,
                'class_name' => $es->schoolClass?->name,
                'total_marks' => (float) $es->total_marks,
                'exam_date' => $sch?->exam_date?->toDateString(),
                'start_time' => $sch?->start_time ? substr($sch->start_time, 0, 5) : null,
                'end_time' => $sch?->end_time ? substr($sch->end_time, 0, 5) : null,
                'duration_minutes' => $sch?->duration_minutes,
                'instructions' => $sch?->instructions,
            ];
        });

        $classes = $examSubjects->pluck('schoolClass')->filter()->unique('id')
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values();

        // Heuristic default for the auto-generator: monthly / unit tests
        // typically run within the period the subject is normally taught.
        $typeSlug = strtolower((string) ($exam->examType?->slug ?? ''));
        $autoModeDefault = (str_contains($typeSlug, 'monthly') || str_contains($typeSlug, 'unit') || str_contains($typeSlug, 'test'))
            ? 'period_based'
            : 'terminal';

        return Inertia::render('Scheduling/Datesheet', [
            'autoModeDefault' => $autoModeDefault,
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'status' => $exam->status,
                'exam_type' => $exam->examType?->name,
                'start_date' => $exam->start_date?->toDateString(),
                'end_date' => $exam->end_date?->toDateString(),
            ],
            'rows' => $rows,
            'classes' => $classes,
        ]);
    }

    /**
     * Save/update schedule rows for the exam.
     */
    public function storeSchedule(Request $request, Exam $exam): RedirectResponse
    {
        // Bound paper dates to the exam window when the exam itself has dates.
        // If the exam has no start/end set we fall back to plain 'date' validation.
        $dateRules = ['nullable', 'date'];
        if ($exam->start_date) {
            $dateRules[] = 'after_or_equal:' . $exam->start_date->toDateString();
        }
        if ($exam->end_date) {
            $dateRules[] = 'before_or_equal:' . $exam->end_date->toDateString();
        }

        $validated = $request->validate([
            'schedules' => ['required', 'array'],
            'schedules.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'schedules.*.school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'schedules.*.exam_date' => $dateRules,
            'schedules.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedules.*.end_time' => ['nullable', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.instructions' => ['nullable', 'string', 'max:1000'],
        ], [
            'schedules.*.exam_date.after_or_equal' => 'Each paper date must fall on or after the exam start date (' . optional($exam->start_date)->format('d M Y') . ').',
            'schedules.*.exam_date.before_or_equal' => 'Each paper date must fall on or before the exam end date (' . optional($exam->end_date)->format('d M Y') . ').',
            'schedules.*.start_time.date_format' => 'Start time must be in HH:MM 24-hour format.',
            'schedules.*.end_time.date_format' => 'End time must be in HH:MM 24-hour format.',
            'schedules.*.end_time.after' => 'End time must be after the start time.',
        ]);

        // ─── Conflict detection ───
        // Two papers can't share the same date + overlapping time window for the
        // same class, otherwise the same students would have two simultaneous
        // exams. Check pairwise within the submitted batch.
        $conflicts = [];
        $rows = array_values($validated['schedules']);
        for ($i = 0; $i < count($rows); $i++) {
            $a = $rows[$i];
            if (empty($a['exam_date']) || empty($a['start_time']) || empty($a['end_time'])) continue;

            $aStart = Carbon::parse($a['exam_date'] . ' ' . $a['start_time']);
            $aEnd = Carbon::parse($a['exam_date'] . ' ' . $a['end_time']);

            for ($j = $i + 1; $j < count($rows); $j++) {
                $b = $rows[$j];
                if (empty($b['exam_date']) || empty($b['start_time']) || empty($b['end_time'])) continue;
                if ((int) $a['school_class_id'] !== (int) $b['school_class_id']) continue;
                if ($a['exam_date'] !== $b['exam_date']) continue;

                $bStart = Carbon::parse($b['exam_date'] . ' ' . $b['start_time']);
                $bEnd = Carbon::parse($b['exam_date'] . ' ' . $b['end_time']);

                // Overlap when start < otherEnd && otherStart < end
                if ($aStart->lt($bEnd) && $bStart->lt($aEnd)) {
                    $cls = SchoolClass::find($a['school_class_id'])?->name ?? "class #{$a['school_class_id']}";
                    $subjA = Subject::find($a['subject_id'])?->name ?? "subject #{$a['subject_id']}";
                    $subjB = Subject::find($b['subject_id'])?->name ?? "subject #{$b['subject_id']}";
                    $conflicts["schedules.{$j}.start_time"] = sprintf(
                        '%s overlaps with %s on %s (%s–%s) for class %s. Pick a different time.',
                        $subjB, $subjA,
                        Carbon::parse($a['exam_date'])->format('d M Y'),
                        $a['start_time'], $a['end_time'], $cls
                    );
                }
            }
        }

        if (!empty($conflicts)) {
            throw \Illuminate\Validation\ValidationException::withMessages($conflicts);
        }

        // ─── Cross-exam conflict detection (soft warning) ───
        // Hard-block within-exam conflicts above. For OTHER exams that already
        // have schedules on the same (class, date, overlapping time), we collect
        // a list and flash it as a warning — the user might genuinely want to
        // run two exams in parallel (e.g. mid-term for Class V while Class VI
        // sits a monthly test in another room) and we don't want to block them.
        $crossExamWarnings = [];
        foreach ($rows as $row) {
            if (empty($row['exam_date']) || empty($row['start_time']) || empty($row['end_time'])) continue;

            $rowStart = Carbon::parse($row['exam_date'] . ' ' . $row['start_time']);
            $rowEnd = Carbon::parse($row['exam_date'] . ' ' . $row['end_time']);

            $clashes = ExamSchedule::where('exam_id', '!=', $exam->id)
                ->where('school_class_id', $row['school_class_id'])
                ->whereDate('exam_date', $row['exam_date'])
                ->with(['exam:id,name', 'subject:id,name'])
                ->get()
                ->filter(function ($other) use ($rowStart, $rowEnd, $row) {
                    if (!$other->start_time || !$other->end_time) return false;
                    $oStart = Carbon::parse($row['exam_date'] . ' ' . substr($other->start_time, 0, 5));
                    $oEnd = Carbon::parse($row['exam_date'] . ' ' . substr($other->end_time, 0, 5));
                    return $rowStart->lt($oEnd) && $oStart->lt($rowEnd);
                });

            foreach ($clashes as $other) {
                $cls = SchoolClass::find($row['school_class_id'])?->name ?? "class #{$row['school_class_id']}";
                $crossExamWarnings[] = sprintf(
                    '%s clashes with "%s" (%s) on %s — both scheduled for %s.',
                    Subject::find($row['subject_id'])?->name ?? "subject #{$row['subject_id']}",
                    $other->exam?->name ?? 'another exam',
                    $other->subject?->name ?? '—',
                    Carbon::parse($row['exam_date'])->format('d M Y'),
                    $cls
                );
            }
        }
        $crossExamWarnings = array_values(array_unique($crossExamWarnings));

        $saved = 0;

        DB::transaction(function () use ($validated, $exam, &$saved) {
            foreach ($validated['schedules'] as $row) {
                // Skip rows with no date (empty row)
                if (empty($row['exam_date']) || empty($row['start_time']) || empty($row['end_time'])) {
                    // If a schedule exists but these fields are empty, we leave it alone.
                    continue;
                }

                $duration = null;
                try {
                    $start = Carbon::parse($row['exam_date'] . ' ' . $row['start_time']);
                    $end = Carbon::parse($row['exam_date'] . ' ' . $row['end_time']);
                    if ($end->gt($start)) {
                        // Carbon 3 made diffInMinutes signed: $later->diffInMinutes($earlier)
                        // returns NEGATIVE. Use the earlier->later direction (or abs).
                        $duration = (int) abs($start->diffInMinutes($end));
                    }
                } catch (\Throwable $e) {
                    $duration = null;
                }

                ExamSchedule::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'subject_id' => $row['subject_id'],
                        'school_class_id' => $row['school_class_id'],
                    ],
                    [
                        'exam_date' => $row['exam_date'],
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
                        'duration_minutes' => $duration,
                        'instructions' => $row['instructions'] ?? null,
                    ]
                );

                $saved++;
            }
        });

        $redirect = redirect()
            ->route('scheduling.datesheet', $exam->id)
            ->with('success', sprintf('%d schedule row(s) saved.', $saved));

        if (!empty($crossExamWarnings)) {
            // Soft warning toast — saving still succeeded, but the user should
            // know another exam already had a paper slotted at the same time.
            // Trim the list at 3 entries to keep the toast readable; rest is
            // implied by the count.
            $shown = array_slice($crossExamWarnings, 0, 3);
            $extra = count($crossExamWarnings) - count($shown);
            $msg = 'Schedule saved with ' . count($crossExamWarnings)
                . ' cross-exam clash' . (count($crossExamWarnings) === 1 ? '' : 'es')
                . ': ' . implode(' | ', $shown)
                . ($extra > 0 ? sprintf(' (+%d more)', $extra) : '');
            $redirect->with('warning', $msg);
        }

        return $redirect;
    }

    /**
     * POST /scheduling/exams/{exam}/datesheet/auto — auto-build the date
     * sheet from exam dates + bell schedule, skipping Sundays + holidays.
     * Admin can still hand-edit on the regular Datesheet screen afterwards.
     */
    public function autoGenerateDatesheet(Request $request, Exam $exam): RedirectResponse
    {
        // Window the generator inside the exam's own [start_date, end_date].
        // Picking a date outside that window was the source of the "17
        // schedule conflicts" report — papers fall through to the default
        // 09:00 because nextWorkingDay returns nothing in-window.
        $dateRules = ['nullable', 'date'];
        if ($exam->start_date) $dateRules[] = 'after_or_equal:' . $exam->start_date->toDateString();
        if ($exam->end_date)   $dateRules[] = 'before_or_equal:' . $exam->end_date->toDateString();

        $validated = $request->validate([
            'mode' => ['required', 'in:terminal,period_based'],
            'start_date' => $dateRules,
            'end_date' => array_merge($dateRules, ['after_or_equal:start_date']),
            'default_start_time' => ['nullable', 'date_format:H:i'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:300'],
            'off_days' => ['nullable', 'array'],
            'off_days.*' => ['integer', 'between:0,6'],
            'holidays' => ['nullable', 'array'],
            'holidays.*' => ['date'],
            'overwrite_existing' => ['boolean'],
        ], [
            'start_date.after_or_equal' => 'Auto-gen start date must fall on or after the exam start date (' . optional($exam->start_date)->format('d M Y') . ').',
            'start_date.before_or_equal' => 'Auto-gen start date must fall on or before the exam end date (' . optional($exam->end_date)->format('d M Y') . ').',
            'end_date.after_or_equal' => 'Auto-gen end date must fall on or after the chosen start date and within the exam window.',
            'end_date.before_or_equal' => 'Auto-gen end date must fall on or before the exam end date (' . optional($exam->end_date)->format('d M Y') . ').',
        ]);

        $service = app(\App\Services\DatesheetGeneratorService::class);
        $summary = $service->generate($exam, $validated);

        $msg = "Auto-generated date sheet: {$summary['scheduled']} paper(s) scheduled";
        if (!empty($summary['warnings'])) {
            $msg .= ' · ' . count($summary['warnings']) . ' warning(s) — ' . implode(' | ', array_slice($summary['warnings'], 0, 2));
        }

        $redirect = redirect()->route('scheduling.datesheet', $exam->id);

        if ($summary['scheduled'] > 0) {
            return $redirect->with('success', $msg);
        }
        return $redirect->with('warning', $msg);
    }

    /**
     * Generate a PDF date sheet grouped by class.
     */
    public function datesheetPdf(Request $request, Exam $exam)
    {
        $exam->load(['examType', 'academicSession', 'schools', 'examController:id,name']);
        $user = $request->user();

        // ─── Resolve which school's date sheet to print ───
        // Principal: their own school. DDO/super-admin: the school they pick
        // via ?school_id=, falling back to the exam's first applicable school
        // (so a single-school exam works without a query string).
        $school = null;
        if ($user->school) {
            $school = $user->school;
        } elseif ($request->filled('school_id')) {
            $school = School::find($request->input('school_id'));
        }
        if (!$school) {
            $school = $exam->schools->first();
        }
        if (!$school) {
            // Last-ditch fallback. Should not happen on a published exam.
            $school = (object) [
                'id' => null, 'name' => 'School', 'code' => '',
                'address' => '', 'phone' => '', 'logo' => null,
                'school_stamp' => null, 'principal_signature' => null,
            ];
        } elseif ($school instanceof School) {
            // Load the principal so the date-sheet can print their name under
            // the signature line.
            $school->loadMissing('principal:id,name,school_id');
        }

        // Pull schedules. We don't filter by school directly — schedules live
        // on (exam, subject, class) and one class belongs to one school. So
        // filter via class.school_id.
        $schedules = ExamSchedule::where('exam_id', $exam->id)
            ->whereHas('schoolClass', fn ($q) => $school->id ? $q->where('school_id', $school->id) : $q)
            ->with(['subject', 'schoolClass.sections'])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        // Group by class — section is implicit (a paper covers the whole class
        // since exam_subjects are mapped to a class, not a section). The PDF
        // shows section names underneath the class header.
        $byClass = $schedules->groupBy(fn ($s) => $s->schoolClass?->name ?? 'Unknown');

        // Sections for each class, so the PDF can show "Class X · Sections A, B"
        $sectionsByClass = $schedules
            ->pluck('schoolClass')
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn ($c) => [
                $c->name => $c->sections->pluck('name')->implode(', ') ?: '—',
            ])
            ->all();

        $pdf = Pdf::loadView('reports.date-sheet', [
            'exam' => $exam,
            'school' => $school,
            'byClass' => $byClass,
            'sectionsByClass' => $sectionsByClass,
            'academicSession' => $exam->academicSession,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("date-sheet-{$exam->slug}.pdf");
    }

    /**
     * List / manage exam rooms for the user's school.
     */
    public function rooms(Request $request): Response
    {
        $user = $request->user();

        $rooms = ExamRoom::query()
            ->with('school')
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->orderBy('school_id')
            ->orderBy('name')
            ->get();

        // Schools list. For non-super-admin, just their own school (if set).
        // The previous code did `[$user->school]->filter()` — calling ->filter()
        // on a raw array which crashes; replaced with collect() wrapper.
        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : collect([$user->school])->filter()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values();

        // Default selected school: for non-super-admin it's their own.
        // For super-admin, default to the first school in the list (or null
        // if no schools exist yet) — otherwise the form silently fails
        // when only one school exists (selector hidden + school_id null).
        $defaultSchoolId = $user->isSuperAdmin()
            ? ($schools->first()['id'] ?? null)
            : $user->school_id;

        return Inertia::render('Scheduling/Rooms', [
            'rooms' => $rooms->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'capacity' => $r->capacity,
                'rows' => $r->rows,
                'cols' => $r->cols,
                'is_active' => (bool) $r->is_active,
                'school_id' => $r->school_id,
                'school_name' => $r->school?->name,
            ])->values(),
            'schools' => $schools,
            'defaultSchoolId' => $defaultSchoolId,
        ]);
    }

    /**
     * Create a room.
     */
    public function storeRoom(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'name'      => ['required', 'string', 'max:100'],
            'capacity'  => ['required', 'integer', 'min:1', 'max:500'],
            'rows'      => ['required', 'integer', 'min:1', 'max:50'],
            'cols'      => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        // Resolve which school owns this room.
        $schoolId = $user->isSuperAdmin()
            ? ($validated['school_id'] ?? $user->school_id)
            : $user->school_id;

        // Throw a real validation error so the form shows it under the
        // school field, instead of a silent flash redirect users miss.
        if (!$schoolId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'school_id' => 'Please choose a school for this exam room.',
            ]);
        }

        ExamRoom::create([
            'school_id' => $schoolId,
            'name'      => $validated['name'],
            'capacity'  => $validated['capacity'],
            'rows'      => $validated['rows'],
            'cols'      => $validated['cols'],
            'is_active' => true,
        ]);

        return redirect()->route('scheduling.rooms')->with('success', 'Exam room created.');
    }

    /**
     * Seating planner for a section.
     */
    public function seating(Exam $exam, Section $section): Response
    {
        $user = request()->user();
        $section->load(['schoolClass.school']);

        if (!$user->isSuperAdmin() && $section->schoolClass?->school_id !== $user->school_id) {
            abort(403, 'You can only manage seating for your school.');
        }

        $students = Student::where('section_id', $section->id)
            ->where('status', 'active')
            ->orderBy('roll_no')
            ->orderBy('name')
            ->get(['id', 'name', 'roll_no', 'admission_no']);

        $schoolId = $section->schoolClass?->school_id ?? $user->school_id;

        $rooms = ExamRoom::active()
            ->where('school_id', $schoolId)
            ->orderBy('name')
            ->get(['id', 'name', 'capacity', 'rows', 'cols']);

        $seats = ExamSeat::where('exam_id', $exam->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->with('room:id,name,rows,cols')
            ->get();

        $assignments = $seats->map(fn ($s) => [
            'id' => $s->id,
            'student_id' => $s->student_id,
            'exam_room_id' => $s->exam_room_id,
            'room_name' => $s->room?->name,
            'seat_number' => $s->seat_number,
            'row_num' => $s->row_num,
            'col_num' => $s->col_num,
        ]);

        return Inertia::render('Scheduling/Seating', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
            ],
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'class_name' => $section->schoolClass?->name,
                'full_name' => ($section->schoolClass?->name ?? '') . ' - ' . $section->name,
            ],
            'students' => $students,
            'rooms' => $rooms,
            'assignments' => $assignments,
        ]);
    }

    /**
     * Auto-assign seats for all students in the section to the chosen room.
     */
    public function autoAssignSeats(Request $request, Exam $exam, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'exam_room_id' => ['required', 'integer', 'exists:exam_rooms,id'],
            'order_by' => ['nullable', 'in:roll,name,random'],
        ]);

        $room = ExamRoom::findOrFail($validated['exam_room_id']);
        $orderBy = $validated['order_by'] ?? 'roll';

        $studentsQuery = Student::where('section_id', $section->id)->where('status', 'active');

        $students = match ($orderBy) {
            'name' => $studentsQuery->orderBy('name')->get(),
            'random' => $studentsQuery->inRandomOrder()->get(),
            default => $studentsQuery->orderByRaw('LENGTH(roll_no), roll_no')->orderBy('name')->get(),
        };

        $capacity = $room->rows * $room->cols;
        if ($students->count() > $capacity) {
            return back()->with('error', sprintf(
                'Not enough capacity: %d students, room capacity is %d (%d×%d).',
                $students->count(), $capacity, $room->rows, $room->cols
            ));
        }

        $count = 0;
        DB::transaction(function () use ($students, $exam, $room, &$count) {
            // Clear existing assignments for these students for this exam
            ExamSeat::where('exam_id', $exam->id)
                ->whereIn('student_id', $students->pluck('id'))
                ->delete();

            $i = 0;
            foreach ($students as $student) {
                $row = intdiv($i, $room->cols) + 1; // 1-indexed
                $col = ($i % $room->cols) + 1;
                $seatNumber = chr(64 + $row) . '-' . str_pad((string) $col, 2, '0', STR_PAD_LEFT);

                ExamSeat::create([
                    'exam_id' => $exam->id,
                    'exam_room_id' => $room->id,
                    'student_id' => $student->id,
                    'seat_number' => $seatNumber,
                    'row_num' => $row,
                    'col_num' => $col,
                ]);
                $i++;
                $count++;
            }
        });

        return redirect()
            ->route('scheduling.seating', ['exam' => $exam->id, 'section' => $section->id])
            ->with('success', sprintf('%d seats assigned in %s.', $count, $room->name));
    }

    /**
     * Remove all seat assignments for this section.
     */
    public function clearSeats(Exam $exam, Section $section): RedirectResponse
    {
        $studentIds = Student::where('section_id', $section->id)->pluck('id');

        $deleted = ExamSeat::where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->delete();

        return redirect()
            ->route('scheduling.seating', ['exam' => $exam->id, 'section' => $section->id])
            ->with('success', sprintf('%d seat assignment(s) cleared.', $deleted));
    }

    /**
     * Seating chart PDF for a room.
     */
    public function seatingPdf(Exam $exam, ExamRoom $room)
    {
        $exam->load(['academicSession']);
        $user = request()->user();

        if (!$user->isSuperAdmin() && $room->school_id !== $user->school_id) {
            abort(403);
        }

        $seats = ExamSeat::where('exam_id', $exam->id)
            ->where('exam_room_id', $room->id)
            ->with(['student:id,name,roll_no,admission_no,section_id', 'student.section:id,name'])
            ->get()
            ->keyBy(fn ($s) => $s->row_num . '-' . $s->col_num);

        // Build grid
        $grid = [];
        for ($r = 1; $r <= $room->rows; $r++) {
            $rowData = [];
            for ($c = 1; $c <= $room->cols; $c++) {
                $key = $r . '-' . $c;
                $seat = $seats->get($key);
                $rowData[] = $seat ? [
                    'seat_number' => $seat->seat_number,
                    'student_name' => $seat->student?->name,
                    'roll_no' => $seat->student?->roll_no,
                    'admission_no' => $seat->student?->admission_no,
                    'section_name' => $seat->student?->section?->name,
                ] : null;
            }
            $grid[] = $rowData;
        }

        $school = $room->school ?? $user->school;

        $pdf = Pdf::loadView('reports.seating-plan', [
            'exam' => $exam,
            'room' => $room,
            'school' => $school,
            'grid' => $grid,
            'academicSession' => $exam->academicSession,
            'totalSeats' => count($seats),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("seating-plan-{$exam->slug}-{$room->id}.pdf");
    }

    /**
     * List invigilator assignments for the exam.
     */
    public function invigilators(Exam $exam): Response
    {
        $user = request()->user();
        $schoolId = $user->isSuperAdmin() ? null : $user->school_id;

        $schedules = ExamSchedule::where('exam_id', $exam->id)
            ->with(['subject', 'schoolClass'])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $rooms = ExamRoom::active()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $teachers = User::query()
            ->where('is_active', true)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher', 'school-admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $assignments = ExamInvigilator::where('exam_id', $exam->id)
            ->with(['schedule.subject', 'schedule.schoolClass', 'room:id,name', 'user:id,name,email'])
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'exam_schedule_id' => $a->exam_schedule_id,
                'exam_room_id' => $a->exam_room_id,
                'user_id' => $a->user_id,
                'role' => $a->role,
                'teacher_name' => $a->user?->name,
                'room_name' => $a->room?->name,
                'subject_name' => $a->schedule?->subject?->name,
                'class_name' => $a->schedule?->schoolClass?->name,
                'exam_date' => $a->schedule?->exam_date?->toDateString(),
                'start_time' => $a->schedule?->start_time ? substr($a->schedule->start_time, 0, 5) : null,
                'end_time' => $a->schedule?->end_time ? substr($a->schedule->end_time, 0, 5) : null,
            ]);

        return Inertia::render('Scheduling/Invigilators', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
            ],
            'schedules' => $schedules->map(fn ($s) => [
                'id' => $s->id,
                'subject_name' => $s->subject?->name,
                'class_name' => $s->schoolClass?->name,
                'exam_date' => $s->exam_date?->toDateString(),
                'start_time' => $s->start_time ? substr($s->start_time, 0, 5) : null,
                'end_time' => $s->end_time ? substr($s->end_time, 0, 5) : null,
            ]),
            'rooms' => $rooms,
            'teachers' => $teachers,
            'assignments' => $assignments,
        ]);
    }

    /**
     * Assign an invigilator to a schedule/room.
     */
    public function storeInvigilator(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'exam_schedule_id' => ['required', 'integer', 'exists:exam_schedules,id'],
            'exam_room_id' => ['required', 'integer', 'exists:exam_rooms,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'in:chief,invigilator,relief'],
        ]);

        ExamInvigilator::create(array_merge($validated, [
            'exam_id' => $exam->id,
        ]));

        return back()->with('success', 'Invigilator assigned.');
    }

    /**
     * Remove an invigilator assignment.
     */
    public function deleteInvigilator(ExamInvigilator $invigilator): RedirectResponse
    {
        $examId = $invigilator->exam_id;
        $invigilator->delete();

        return redirect()
            ->route('scheduling.invigilators', $examId)
            ->with('success', 'Invigilator removed.');
    }

    /**
     * PDF duty chart grouped by teacher.
     */
    public function invigilatorDutyPdf(Exam $exam)
    {
        $exam->load(['academicSession']);
        $user = request()->user();
        $school = $user->school ?? (object) ['name' => 'All Schools', 'address' => '', 'phone' => '', 'logo' => null];

        $assignments = ExamInvigilator::where('exam_id', $exam->id)
            ->with(['user', 'room', 'schedule.subject', 'schedule.schoolClass'])
            ->get();

        $byTeacher = $assignments->groupBy(fn ($a) => $a->user?->name ?? 'Unassigned');

        $pdf = Pdf::loadView('reports.invigilator-duty', [
            'exam' => $exam,
            'school' => $school,
            'byTeacher' => $byTeacher,
            'academicSession' => $exam->academicSession,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("invigilator-duty-{$exam->slug}.pdf");
    }

    /**
     * Admit card landing — section selector.
     */
    public function admitCards(Exam $exam): Response
    {
        $user = request()->user();

        // Sort by stage / grade order, not alphabetical — ECD → Nursery → Prep
        // → One → Two → … → Second Year. Uses the school_classes.sort_order
        // column set up earlier; the global gradeOrder scope already does this
        // by default, but we pass through ->reorder() in case a caller mutated it.
        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with(['sections' => function ($q) {
                $q->withCount('students');
            }])
            ->reorder()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Scheduling/AdmitCards', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
            ],
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sections' => $c->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'students_count' => $s->students_count ?? 0,
                ]),
            ]),
        ]);
    }

    /**
     * Generate a multi-page PDF of admit cards for a section.
     */
    public function downloadAdmitCards(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);

        $section = Section::with(['schoolClass.school'])->findOrFail($validated['section_id']);

        $user = $request->user();
        if (!$user->isSuperAdmin() && $section->schoolClass?->school_id !== $user->school_id) {
            abort(403);
        }

        $students = Student::where('section_id', $section->id)
            ->where('status', 'active')
            ->orderBy('roll_no')
            ->get();

        // Schedules for this class
        $schedules = ExamSchedule::where('exam_id', $exam->id)
            ->where('school_class_id', $section->school_class_id)
            ->with('subject')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        // Seat assignments for these students
        $seats = ExamSeat::where('exam_id', $exam->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->with('room:id,name')
            ->get()
            ->keyBy('student_id');

        $school = $section->schoolClass?->school;

        // Build a per-student data set. Each card gets a real scannable QR
        // (BaconQrCode SVG output — no GD/Imagick required) embedded as inline
        // SVG, plus the verification code text for the human-readable copy.
        $cards = $students->map(function ($student) use ($exam, $schedules, $seats) {
            $seat = $seats->get($student->id);
            $code = $this->buildAdmitCode($exam->id, $student->id);
            return [
                'student' => $student,
                'seat' => $seat,
                'code' => $code,
                'qrSvg' => $this->buildQrHtml($code),
            ];
        });

        $exam->load(['examType', 'academicSession', 'examController:id,name']);
        // Load relation fallback for the principal name (in case the school
        // hasn't filled in the free-text principal_name column yet).
        $school?->loadMissing('principal:id,name,school_id');

        $pdf = Pdf::loadView('reports.admit-cards', [
            'exam' => $exam,
            'school' => $school,
            'section' => $section,
            'schoolClass' => $section->schoolClass,
            'academicSession' => $exam->academicSession,
            'cards' => $cards,
            'schedules' => $schedules,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("admit-cards-{$exam->slug}-{$section->slug}.pdf");
    }

    /**
     * Download admit cards for EVERY student in an exam — all sections, all
     * classes — as a single consolidated PDF. Optional ?school_class_id=
     * narrows to one class.
     *
     * Same blade template as the per-section version (one card per page);
     * we just feed it a wider student list. Avoids exam offices having to
     * download per-section PDFs and merge them by hand.
     */
    public function downloadBulkAdmitCards(Request $request, Exam $exam)
    {
        $user = $request->user();
        // School-scope guard. School-admins see only their own school's
        // students; super-admin sees everything.
        $schoolId = $user->isSuperAdmin() ? null : $user->school_id;

        $validated = $request->validate([
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
        ]);

        // Pull every student in the exam's scope. Uses the same school +
        // class filtering as the per-section version, just without the
        // section_id constraint.
        $studentsQ = Student::query()
            ->where('status', 'active')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when(!empty($validated['school_class_id']),
                fn ($q) => $q->where('school_class_id', $validated['school_class_id']))
            // Only include students whose class is part of this exam.
            // examSubjects lives on Exam, not SchoolClass — narrow by
            // school_class_id IN (exam's class set) instead.
            ->whereIn('school_class_id', ExamSubject::where('exam_id', $exam->id)->pluck('school_class_id'))
            ->with(['schoolClass', 'section'])
            ->orderBy('school_class_id')
            ->orderBy('section_id')
            ->orderBy('roll_no');

        $students = $studentsQ->get();

        if ($students->isEmpty()) {
            return redirect()->back()->with('error', 'No students found for this exam in your scope.');
        }

        // Schedules are per-class; pull all that the exam touches.
        $schedules = ExamSchedule::where('exam_id', $exam->id)
            ->when($schoolId, fn ($q) => $q->whereHas('schoolClass', fn ($q2) => $q2->where('school_id', $schoolId)))
            ->with('subject', 'schoolClass')
            ->orderBy('school_class_id')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $seats = ExamSeat::where('exam_id', $exam->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->with('room:id,name')
            ->get()
            ->keyBy('student_id');

        $school = $schoolId ? School::find($schoolId) : $students->first()?->school;

        $cards = $students->map(function ($student) use ($exam, $seats) {
            $seat = $seats->get($student->id);
            $code = $this->buildAdmitCode($exam->id, $student->id);
            return [
                'student' => $student,
                'seat' => $seat,
                'code' => $code,
                'qrSvg' => $this->buildQrHtml($code),
            ];
        });

        $exam->load(['examType', 'academicSession', 'examController:id,name']);
        $school?->loadMissing('principal:id,name,school_id');

        // Schedules grouped by class so each class's section in the PDF
        // shows the right schedule. The blade template loops cards but
        // we currently pass a flat schedule list — for the bulk version
        // we send the union (all classes), and the template still works
        // because $schedules is iterated per-card.
        $pdf = Pdf::loadView('reports.admit-cards', [
            'exam' => $exam,
            'school' => $school,
            'section' => null,                  // mixed sections in one doc
            'schoolClass' => null,
            'academicSession' => $exam->academicSession,
            'cards' => $cards,
            'schedules' => $schedules,
        ])->setPaper('a4', 'portrait');

        $suffix = !empty($validated['school_class_id'])
            ? '-class-' . $validated['school_class_id']
            : '-all-classes';

        return $pdf->stream("admit-cards-{$exam->slug}{$suffix}.pdf");
    }

    /**
     * Public QR verification page.
     */
    public function verifyAdmit(string $code)
    {
        $parsed = $this->parseAdmitCode($code);

        if (!$parsed) {
            return Inertia::render('Scheduling/Verify', [
                'valid' => false,
                'message' => 'Invalid or malformed admit card code.',
            ]);
        }

        $exam = Exam::with('academicSession')->find($parsed['exam_id']);
        $student = Student::with(['schoolClass', 'section', 'school'])->find($parsed['student_id']);

        if (!$exam || !$student) {
            return Inertia::render('Scheduling/Verify', [
                'valid' => false,
                'message' => 'Admit card record not found.',
            ]);
        }

        // Verify signature
        $expected = $this->buildAdmitCode($exam->id, $student->id);
        if (!hash_equals($expected, $code)) {
            return Inertia::render('Scheduling/Verify', [
                'valid' => false,
                'message' => 'Admit card verification failed.',
            ]);
        }

        $schedules = ExamSchedule::where('exam_id', $exam->id)
            ->where('school_class_id', $student->school_class_id)
            ->with('subject')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $seat = ExamSeat::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->with('room:id,name')
            ->first();

        return Inertia::render('Scheduling/Verify', [
            'valid' => true,
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'photo' => $student->photo ? asset('storage/' . $student->photo) : null,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'class_name' => $student->schoolClass?->name,
                'section_name' => $student->section?->name,
                'school_name' => $student->school?->name,
            ],
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'session' => $exam->academicSession?->name,
            ],
            'schedules' => $schedules->map(fn ($s) => [
                'subject' => $s->subject?->name,
                'exam_date' => $s->exam_date?->toDateString(),
                'start_time' => $s->start_time ? substr($s->start_time, 0, 5) : null,
                'end_time' => $s->end_time ? substr($s->end_time, 0, 5) : null,
            ]),
            'seat' => $seat ? [
                'seat_number' => $seat->seat_number,
                'room_name' => $seat->room?->name,
            ] : null,
        ]);
    }

    /**
     * Build a tamper-resistant admit code from exam_id + student_id.
     */
    public static function buildAdmitCode(int $examId, int $studentId): string
    {
        $payload = $examId . '-' . $studentId;
        $sig = substr(hash_hmac('sha256', $payload, config('app.key') ?? 'admit-salt'), 0, 10);
        return strtoupper(base_convert($examId, 10, 36))
            . 'X' . strtoupper(base_convert($studentId, 10, 36))
            . 'X' . strtoupper($sig);
    }

    /**
     * Parse an admit code back to [exam_id, student_id] (without verifying signature).
     */
    protected function parseAdmitCode(string $code): ?array
    {
        $parts = explode('X', strtoupper($code));
        if (count($parts) !== 3) {
            return null;
        }
        $examId = (int) base_convert($parts[0], 36, 10);
        $studentId = (int) base_convert($parts[1], 36, 10);
        if ($examId <= 0 || $studentId <= 0) {
            return null;
        }
        return ['exam_id' => $examId, 'student_id' => $studentId];
    }

    /**
     * Render a real scannable QR code as inline HTML for dompdf.
     *
     * dompdf v3 has incomplete SVG support — BaconQrCode's path-based SVG
     * silently rendered as a blank box. Workaround: use BaconQrCode purely as
     * a matrix encoder, then render each black module as an absolutely-
     * positioned <div>. dompdf handles position:absolute with explicit pixel
     * dimensions reliably.
     *
     * Encodes the verify URL (so scanning the QR opens the verification page).
     */
    protected function buildQrHtml(string $code): string
    {
        $payload = url('/verify/admit/' . $code);

        $qrCode = \BaconQrCode\Encoder\Encoder::encode(
            $payload,
            \BaconQrCode\Common\ErrorCorrectionLevel::M(),
            'utf-8'
        );

        $matrix = $qrCode->getMatrix();
        $size = $matrix->getWidth();

        // Fill the entire .qr-box inner area (≈ 102px after 4px padding on
        // each side of the 110px wrap). Use float pixel widths so that 21-cell
        // and 25-cell matrices both expand to the same final size — no gap
        // between QR and box border.
        $targetPx = 102.0;
        $cellPx = $targetPx / $size; // float ok — dompdf accepts decimal px

        $dots = '';
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->get($x, $y) !== 1) continue;
                $dots .= sprintf(
                    '<div style="position:absolute;left:%.3fpx;top:%.3fpx;width:%.3fpx;height:%.3fpx;background:#0f172a;"></div>',
                    $x * $cellPx, $y * $cellPx, $cellPx, $cellPx
                );
            }
        }

        return sprintf(
            '<div style="position:relative;width:%.2fpx;height:%.2fpx;background:#fff;display:inline-block;">%s</div>',
            $targetPx, $targetPx, $dots
        );
    }
}
