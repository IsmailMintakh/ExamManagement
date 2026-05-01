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
            ->when($schoolId, fn ($q) => $q->whereHas('schools', fn ($q2) => $q2->where('schools.id', $schoolId)))
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

        return Inertia::render('Scheduling/Datesheet', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'status' => $exam->status,
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
        $validated = $request->validate([
            'schedules' => ['required', 'array'],
            'schedules.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'schedules.*.school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'schedules.*.exam_date' => ['nullable', 'date'],
            'schedules.*.start_time' => ['nullable'],
            'schedules.*.end_time' => ['nullable'],
            'schedules.*.instructions' => ['nullable', 'string', 'max:1000'],
        ]);

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
                        $duration = $end->diffInMinutes($start);
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

        return redirect()
            ->route('scheduling.datesheet', $exam->id)
            ->with('success', sprintf('%d schedule row(s) saved.', $saved));
    }

    /**
     * Generate a PDF date sheet grouped by class.
     */
    public function datesheetPdf(Exam $exam)
    {
        $exam->load(['examType', 'academicSession']);
        $user = request()->user();

        $schedules = ExamSchedule::where('exam_id', $exam->id)
            ->with(['subject', 'schoolClass'])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $byClass = $schedules->groupBy(fn ($s) => $s->schoolClass?->name ?? 'Unknown');

        $school = $user->school ?? (object) [
            'name' => 'All Schools',
            'address' => '',
            'phone' => '',
            'logo' => null,
        ];

        $pdf = Pdf::loadView('reports.date-sheet', [
            'exam' => $exam,
            'school' => $school,
            'byClass' => $byClass,
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

        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : collect([$user->school]->filter())->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]);

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
            'defaultSchoolId' => $user->isSuperAdmin() ? null : $user->school_id,
        ]);
    }

    /**
     * Create a room.
     */
    public function storeRoom(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'school_id' => ['required_if:super_admin,true', 'nullable', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'rows' => ['required', 'integer', 'min:1', 'max:50'],
            'cols' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $schoolId = $user->isSuperAdmin()
            ? ($validated['school_id'] ?? $user->school_id)
            : $user->school_id;

        if (!$schoolId) {
            return back()->with('error', 'A school must be selected.');
        }

        ExamRoom::create([
            'school_id' => $schoolId,
            'name' => $validated['name'],
            'capacity' => $validated['capacity'],
            'rows' => $validated['rows'],
            'cols' => $validated['cols'],
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

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with(['sections' => function ($q) {
                $q->withCount('students');
            }])
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

        // Build a per-student data set
        $cards = $students->map(function ($student) use ($exam, $schedules, $seats) {
            $seat = $seats->get($student->id);
            $code = $this->buildAdmitCode($exam->id, $student->id);
            return [
                'student' => $student,
                'seat' => $seat,
                'code' => $code,
            ];
        });

        $exam->load(['examType', 'academicSession']);

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
}
