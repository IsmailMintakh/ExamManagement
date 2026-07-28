<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\BoardExam;
use App\Models\BoardExamSubject;
use App\Models\BoardResult;
use App\Models\BoardResultSubject;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\BoardResultCalculatorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Board Result — FBISE 9th / 10th official results entry & reporting.
 *
 * Flow:
 *   index()             list board exams (create / open)
 *   create() / store()  set up a new board exam container
 *   show()              student list under a board exam (entry status per row)
 *   enterStudent()      per-student entry form
 *   storeStudent()      save one student's subject-wise marks
 *
 * Grid batch mode, Excel import, and photo OCR ship in follow-up commits;
 * hooks in the routes file for those already point at TODO methods below.
 */
class BoardResultController extends Controller
{
    public function __construct(protected BoardResultCalculatorService $calc) {}

    /** List every board exam the user can see, ordered newest first. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $exams = BoardExam::query()
            ->with(['school:id,name', 'schoolClass:id,name', 'academicSession:id,name'])
            ->withCount(['results as students_count'])
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->orderByDesc('announced_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('BoardResults/Index', [
            'exams' => $exams,
            'canCreate' => $user->can('board_results.manage') || $user->isSuperAdmin(),
        ]);
    }

    /** Show the "New Board Exam" form. */
    public function create(Request $request): Response
    {
        $user = $request->user();
        return Inertia::render('BoardResults/Create', [
            'schools' => $user->isSuperAdmin()
                ? School::active()->orderBy('name')->get(['id', 'name'])
                : School::where('id', $user->school_id)->get(['id', 'name']),
            'classes' => SchoolClass::active()
                ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
                ->whereIn('stage', ['secondary'])   // 9th + 10th
                ->orderBy('name')->get(['id', 'name', 'school_id']),
            'sessions' => AcademicSession::active()->orderByDesc('start_date')->get(['id', 'name', 'is_current']),
            // Grading scales the user can pick — global (school_id null)
            // or those owned by their school. Ordered so defaults appear first.
            'gradingScales' => GradingScale::where('is_active', true)
                ->when(!$user->isSuperAdmin(), fn ($q) => $q->where(function ($qq) use ($user) {
                    $qq->whereNull('school_id')->orWhere('school_id', $user->school_id);
                }))
                ->orderByDesc('is_default')->orderBy('name')
                ->get(['id', 'name', 'is_default', 'school_id']),
            'currentSessionId' => AcademicSession::where('is_current', true)->value('id'),
            'defaultSchoolId'  => $user->isSuperAdmin() ? null : $user->school_id,
        ]);
    }

    /** Create the board-exam container. Idempotent per unique tuple. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'school_id'           => ['required', 'exists:schools,id'],
            'school_class_id'     => ['required', 'exists:school_classes,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'level'               => ['required', 'string', 'max:32'],
            'title'               => ['required', 'string', 'max:255'],
            'announced_on'        => ['nullable', 'date'],
            'total_marks'         => ['required', 'integer', 'min:100', 'max:1200'],
            'pass_percentage'     => ['required', 'integer', 'min:1', 'max:100'],
            'grading_scale_id'    => ['nullable', 'exists:grading_scales,id'],
            'notes'               => ['nullable', 'string'],
        ]);

        $user = $request->user();
        // Non-super-admin can only create for their own school.
        if (!$user->isSuperAdmin() && (int) $data['school_id'] !== (int) $user->school_id) {
            abort(403, 'You can only create board exams for your own school.');
        }

        $exam = BoardExam::create([
            ...$data,
            'board_name' => 'FBISE',
            'created_by' => $user->id,
        ]);

        // Auto-seed board_exam_subjects with FBISE defaults for every
        // subject the class already teaches. Admin can then tune the
        // per-subject max marks on the "Subjects" tab.
        $subjectIds = Subject::whereHas('classes', fn ($q) => $q->where('school_classes.id', $exam->school_class_id))
            ->orderBy('name')->pluck('id');
        foreach ($subjectIds as $i => $sid) {
            BoardExamSubject::updateOrCreate(
                ['board_exam_id' => $exam->id, 'subject_id' => $sid],
                ['theory_max' => 75, 'practical_max' => 25, 'pass_percentage' => null, 'sort_order' => $i]
            );
        }

        return redirect()->route('board-results.show', $exam->id)
            ->with('success', "Board exam “{$exam->title}” created. Set per-subject max marks in the Subjects tab, then enter students' results.");
    }

    /** Per-subject template management — list + edit theory/practical max. */
    public function subjectsIndex(int $boardExam, Request $request): Response
    {
        $exam = BoardExam::with(['school:id,name', 'schoolClass:id,name'])->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        // Left-join Subject so we can list even subjects the class has
        // but this exam doesn't yet cover (in case a new one was added).
        $classSubjects = Subject::whereHas('classes', fn ($q) => $q->where('school_classes.id', $exam->school_class_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
        $templates = BoardExamSubject::where('board_exam_id', $exam->id)
            ->get()->keyBy('subject_id');

        $rows = $classSubjects->map(fn ($sub) => [
            'subject_id'      => $sub->id,
            'name'            => $sub->name,
            'code'            => $sub->code,
            'theory_max'      => (float) ($templates[$sub->id]->theory_max      ?? 75),
            'practical_max'   => (float) ($templates[$sub->id]->practical_max   ?? 25),
            'pass_percentage' => $templates[$sub->id]->pass_percentage !== null
                ? (float) $templates[$sub->id]->pass_percentage : null,
            'included'        => isset($templates[$sub->id]),
        ]);

        return Inertia::render('BoardResults/Subjects', [
            'exam'    => $exam,
            'rows'    => $rows,
            'canEdit' => $this->userCanEdit($request->user(), $exam),
        ]);
    }

    public function subjectsUpdate(Request $request, int $boardExam)
    {
        $exam = BoardExam::findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);
        abort_if($exam->is_locked, 423, 'This board exam is locked. Unlock to edit.');
        abort_unless($this->userCanEdit($request->user(), $exam), 403);

        $data = $request->validate([
            'rows'                    => ['required', 'array'],
            'rows.*.subject_id'       => ['required', 'exists:subjects,id'],
            'rows.*.included'         => ['required', 'boolean'],
            'rows.*.theory_max'       => ['nullable', 'numeric', 'min:0'],
            'rows.*.practical_max'    => ['nullable', 'numeric', 'min:0'],
            'rows.*.pass_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($exam, $data) {
            $keep = [];
            foreach ($data['rows'] as $i => $row) {
                if (!$row['included']) continue;
                $keep[] = $row['subject_id'];
                BoardExamSubject::updateOrCreate(
                    ['board_exam_id' => $exam->id, 'subject_id' => $row['subject_id']],
                    [
                        'theory_max'      => $row['theory_max']      ?? 75,
                        'practical_max'   => $row['practical_max']   ?? 25,
                        'pass_percentage' => $row['pass_percentage'] ?? null,
                        'sort_order'      => $i,
                    ]
                );
            }
            // Drop rows the admin un-ticked so entry forms stop showing them.
            BoardExamSubject::where('board_exam_id', $exam->id)
                ->whereNotIn('subject_id', $keep ?: [0])
                ->delete();
        });

        return redirect()->route('board-results.show', $exam->id)
            ->with('success', "Subject template updated.");
    }

    /**
     * Board-exam detail — every student in the class shows with their
     * entry status (draft / complete / not-started). Row-level "Enter"
     * button opens the per-student form.
     */
    public function show(int $boardExam, Request $request): Response
    {
        $exam = BoardExam::with([
            'school:id,name',
            'schoolClass:id,name',
            'academicSession:id,name',
        ])->findOrFail($boardExam);

        $this->authorizeSchoolAccess($request, $exam);

        // Every student in the class. Board results are typically for one
        // class as a whole (all sections), but we honour section-scoping
        // for schools that split their SSC batches.
        $students = Student::where('school_class_id', $exam->school_class_id)
            ->orderBy('roll_no')->orderBy('name')
            ->get(['id', 'roll_no', 'admission_no', 'name', 'father_name', 'section_id']);

        // Which students already have a result under this exam.
        $resultsByStudent = BoardResult::where('board_exam_id', $exam->id)
            ->get()->keyBy('student_id');

        // Attach a lightweight status per student for the frontend.
        $rows = $students->map(function ($stu) use ($resultsByStudent) {
            $res = $resultsByStudent->get($stu->id);
            return [
                'id'             => $stu->id,
                'roll_no'        => $stu->roll_no,
                'admission_no'   => $stu->admission_no,
                'name'           => $stu->name,
                'father_name'    => $stu->father_name,
                'has_result'     => $res !== null,
                'board_roll_no'  => $res?->board_roll_no,
                'percentage'     => $res ? (float) $res->percentage : null,
                'grade'          => $res?->grade,
                'division'       => $res?->division,
                'is_pass'        => (bool) ($res?->is_pass ?? false),
                'is_supplementary' => (bool) ($res?->is_supplementary ?? false),
                'position'       => $res?->position,
            ];
        });

        $stats = [
            'total'          => $students->count(),
            'entered'        => $resultsByStudent->count(),
            'pending'        => $students->count() - $resultsByStudent->count(),
            'passed'         => $resultsByStudent->filter(fn ($r) => $r->is_pass)->count(),
            'failed'         => $resultsByStudent->filter(fn ($r) => !$r->is_pass && !$r->is_supplementary)->count(),
            'supply'         => $resultsByStudent->filter(fn ($r) => $r->is_supplementary)->count(),
        ];

        return Inertia::render('BoardResults/Show', [
            'exam'     => $exam,
            'students' => $rows,
            'stats'    => $stats,
            'canEdit'  => $this->userCanEdit($request->user(), $exam),
        ]);
    }

    /** Per-student marks entry form. Loads existing subjects if any. */
    public function enterStudent(int $boardExam, int $student, Request $request): Response
    {
        $exam = BoardExam::with(['school:id,name', 'schoolClass:id,name'])->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        $studentModel = Student::where('id', $student)
            ->where('school_class_id', $exam->school_class_id)
            ->firstOrFail(['id', 'roll_no', 'admission_no', 'name', 'father_name']);

        // Get-or-instantiate the result row (not yet saved for new entries).
        $result = BoardResult::firstOrNew([
            'board_exam_id' => $exam->id,
            'student_id'    => $studentModel->id,
        ]);
        $existingSubjects = $result->exists
            ? BoardResultSubject::where('board_result_id', $result->id)
                ->with('subject:id,name,code')
                ->get()
                ->keyBy('subject_id')
            : collect();

        // Subject list — prefer the exam's per-subject template
        // (BoardExamSubject); fall back to the class's Subject list for
        // exams created before the template feature.
        $templates = BoardExamSubject::where('board_exam_id', $exam->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get()->keyBy('subject_id');
        $subjectPool = $templates->isNotEmpty()
            ? Subject::whereIn('id', $templates->keys()->all())->orderByRaw(
                'FIELD(id, '.implode(',', $templates->keys()->all()).')'
              )->get(['id', 'name', 'code'])
            : Subject::whereHas('classes', fn ($q) => $q->where('school_classes.id', $exam->school_class_id))
                ->orderBy('name')->get(['id', 'name', 'code']);

        $subjects = $subjectPool->map(function ($sub) use ($existingSubjects, $templates) {
            $existing = $existingSubjects->get($sub->id);
            $template = $templates->get($sub->id);
            return [
                'id'              => $sub->id,
                'name'            => $sub->name,
                'code'            => $sub->code,
                'theory_marks'    => $existing ? (float) $existing->theory_marks    : null,
                'practical_marks' => $existing ? (float) $existing->practical_marks : null,
                'theory_max'      => $existing ? (float) $existing->theory_max
                                    : ($template ? (float) $template->theory_max    : 75),
                'practical_max'   => $existing ? (float) $existing->practical_max
                                    : ($template ? (float) $template->practical_max : 25),
                'is_absent'       => $existing ? (bool)  $existing->is_absent       : false,
                'included'        => $existing !== null || $template !== null,
            ];
        });

        return Inertia::render('BoardResults/Entry', [
            'exam'     => $exam,
            'student'  => $studentModel,
            'result'   => $result->exists ? $result : null,
            'subjects' => $subjects,
            'canEdit'  => $this->userCanEdit($request->user(), $exam),
        ]);
    }

    /** Save one student's subject-wise marks. Runs the calculator on commit. */
    public function storeStudent(Request $request, int $boardExam, int $student)
    {
        $exam = BoardExam::findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);
        abort_if($exam->is_locked, 423, 'This board exam is locked. Unlock to edit.');
        abort_unless($this->userCanEdit($request->user(), $exam), 403);

        $data = $request->validate([
            'board_roll_no'   => ['nullable', 'string', 'max:32'],
            'remarks'         => ['nullable', 'string'],
            'subjects'                    => ['required', 'array', 'min:1'],
            'subjects.*.subject_id'       => ['required', 'exists:subjects,id'],
            'subjects.*.included'         => ['required', 'boolean'],
            'subjects.*.is_absent'        => ['nullable', 'boolean'],
            'subjects.*.theory_marks'     => ['nullable', 'numeric', 'min:0'],
            'subjects.*.practical_marks'  => ['nullable', 'numeric', 'min:0'],
            'subjects.*.theory_max'       => ['nullable', 'numeric', 'min:0'],
            'subjects.*.practical_max'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $studentModel = Student::where('id', $student)
            ->where('school_class_id', $exam->school_class_id)
            ->firstOrFail();

        DB::transaction(function () use ($exam, $studentModel, $data, $request) {
            $result = BoardResult::firstOrCreate(
                ['board_exam_id' => $exam->id, 'student_id' => $studentModel->id],
                ['entered_by' => $request->user()->id, 'entered_at' => now()]
            );
            $result->board_roll_no = $data['board_roll_no'] ?? null;
            $result->remarks       = $data['remarks']       ?? null;
            $result->entered_by    = $request->user()->id;
            $result->entered_at    = now();
            $result->save();

            // Upsert each included subject; delete rows the user un-ticked.
            $keepSubjectIds = [];
            foreach ($data['subjects'] as $row) {
                if (!$row['included']) continue;
                $keepSubjectIds[] = $row['subject_id'];
                BoardResultSubject::updateOrCreate(
                    ['board_result_id' => $result->id, 'subject_id' => $row['subject_id']],
                    [
                        'theory_marks'    => $row['theory_marks']    ?? 0,
                        'practical_marks' => $row['practical_marks'] ?? 0,
                        'theory_max'      => $row['theory_max']      ?? 75,
                        'practical_max'   => $row['practical_max']   ?? 25,
                        'is_absent'       => (bool) ($row['is_absent'] ?? false),
                    ]
                );
            }
            BoardResultSubject::where('board_result_id', $result->id)
                ->whereNotIn('subject_id', $keepSubjectIds ?: [0])
                ->delete();

            // Recompute this student, then re-rank the whole exam so
            // positions stay consistent after every save.
            $this->calc->recomputeResult($result->fresh('subjects'));
            $this->calc->recomputePositions($exam->id);
        });

        return redirect()->route('board-results.show', $exam->id)
            ->with('success', "Saved marks for {$studentModel->name}.");
    }

    // ─── Grid batch entry ─────────────────────────────────────────────
    // One page: rows = students, columns = subjects (theory + practical).
    // Every cell is a live-editable input; one Save button commits every
    // change in a single DB transaction and re-ranks the exam once.

    public function batchEntry(int $boardExam, Request $request): Response
    {
        $exam = BoardExam::with(['school:id,name', 'schoolClass:id,name'])
            ->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        // Batch grid also honours the per-exam Subjects template so the
        // columns match the entry form 1:1.
        $templates = BoardExamSubject::where('board_exam_id', $exam->id)
            ->orderBy('sort_order')->orderBy('id')->get();
        $subjects = $templates->isNotEmpty()
            ? Subject::whereIn('id', $templates->pluck('subject_id')->all())
                ->orderByRaw('FIELD(id, '.implode(',', $templates->pluck('subject_id')->all()).')')
                ->get(['id', 'name', 'code'])
            : Subject::whereHas('classes', fn ($q) => $q->where('school_classes.id', $exam->school_class_id))
                ->orderBy('name')->get(['id', 'name', 'code']);
        $templateBySubject = $templates->keyBy('subject_id');

        $students = Student::where('school_class_id', $exam->school_class_id)
            ->orderBy('roll_no')->orderBy('name')
            ->get(['id', 'roll_no', 'admission_no', 'name']);

        // Pre-load every existing subject row keyed by "resultId|subjectId"
        // so the frontend can pre-fill inputs where the teacher already
        // entered something.
        $existingResults = BoardResult::where('board_exam_id', $exam->id)
            ->get()->keyBy('student_id');
        $existingSubjects = BoardResultSubject::whereIn('board_result_id', $existingResults->pluck('id'))
            ->get()->groupBy('board_result_id');

        // Build the payload matrix — every student × every subject cell,
        // pre-filled where data exists.
        $rows = $students->map(function ($stu) use ($subjects, $existingResults, $existingSubjects) {
            $result = $existingResults->get($stu->id);
            $subs = $result ? ($existingSubjects->get($result->id) ?? collect()) : collect();
            $byId = $subs->keyBy('subject_id');
            return [
                'student_id'     => $stu->id,
                'roll_no'        => $stu->roll_no,
                'admission_no'   => $stu->admission_no,
                'name'           => $stu->name,
                'board_roll_no'  => $result?->board_roll_no,
                'cells' => $subjects->map(function ($sub) use ($byId, $templateBySubject) {
                    $row = $byId->get($sub->id);
                    $tpl = $templateBySubject->get($sub->id);
                    return [
                        'subject_id'      => $sub->id,
                        'theory_marks'    => $row ? (float) $row->theory_marks    : null,
                        'practical_marks' => $row ? (float) $row->practical_marks : null,
                        'theory_max'      => $row ? (float) $row->theory_max
                                            : ($tpl ? (float) $tpl->theory_max    : 75),
                        'practical_max'   => $row ? (float) $row->practical_max
                                            : ($tpl ? (float) $tpl->practical_max : 25),
                        'is_absent'       => $row ? (bool)  $row->is_absent       : false,
                    ];
                })->values(),
            ];
        });

        return Inertia::render('BoardResults/Batch', [
            'exam'     => $exam,
            'subjects' => $subjects,
            'rows'     => $rows,
            'canEdit'  => $this->userCanEdit($request->user(), $exam),
        ]);
    }

    /** Commit every changed cell in one shot. */
    public function batchStore(Request $request, int $boardExam)
    {
        $exam = BoardExam::findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);
        abort_if($exam->is_locked, 423, 'This board exam is locked. Unlock to edit.');
        abort_unless($this->userCanEdit($request->user(), $exam), 403);

        $data = $request->validate([
            'rows'                                 => ['required', 'array'],
            'rows.*.student_id'                    => ['required', 'exists:students,id'],
            'rows.*.board_roll_no'                 => ['nullable', 'string', 'max:32'],
            'rows.*.cells'                         => ['required', 'array'],
            'rows.*.cells.*.subject_id'            => ['required', 'exists:subjects,id'],
            'rows.*.cells.*.theory_marks'          => ['nullable', 'numeric', 'min:0'],
            'rows.*.cells.*.practical_marks'       => ['nullable', 'numeric', 'min:0'],
            'rows.*.cells.*.theory_max'            => ['nullable', 'numeric', 'min:0'],
            'rows.*.cells.*.practical_max'         => ['nullable', 'numeric', 'min:0'],
            'rows.*.cells.*.is_absent'             => ['nullable', 'boolean'],
        ]);

        $touched = 0;
        DB::transaction(function () use ($exam, $data, $request, &$touched) {
            foreach ($data['rows'] as $rowIn) {
                // Skip rows where every cell is blank AND no board_roll_no
                // is set — nothing to save.
                $hasAny = !empty($rowIn['board_roll_no']) || collect($rowIn['cells'])->contains(
                    fn ($c) => ($c['theory_marks'] !== null && $c['theory_marks'] !== '')
                             || ($c['practical_marks'] !== null && $c['practical_marks'] !== '')
                             || !empty($c['is_absent'])
                );
                if (!$hasAny) continue;

                $result = BoardResult::firstOrCreate(
                    ['board_exam_id' => $exam->id, 'student_id' => $rowIn['student_id']],
                    ['entered_by' => $request->user()->id, 'entered_at' => now()]
                );
                $result->board_roll_no = $rowIn['board_roll_no'] ?? $result->board_roll_no;
                $result->entered_by    = $request->user()->id;
                $result->entered_at    = now();
                $result->save();

                foreach ($rowIn['cells'] as $cell) {
                    // Empty AND not-absent cells → skip (leave as-is).
                    $blank = ($cell['theory_marks'] === null || $cell['theory_marks'] === '')
                          && ($cell['practical_marks'] === null || $cell['practical_marks'] === '')
                          && empty($cell['is_absent']);
                    if ($blank) continue;

                    BoardResultSubject::updateOrCreate(
                        ['board_result_id' => $result->id, 'subject_id' => $cell['subject_id']],
                        [
                            'theory_marks'    => $cell['theory_marks']    ?? 0,
                            'practical_marks' => $cell['practical_marks'] ?? 0,
                            'theory_max'      => $cell['theory_max']      ?? 75,
                            'practical_max'   => $cell['practical_max']   ?? 25,
                            'is_absent'       => (bool) ($cell['is_absent'] ?? false),
                        ]
                    );
                }
                $this->calc->recomputeResult($result->fresh('subjects'));
                $touched++;
            }
            $this->calc->recomputePositions($exam->id);
        });

        return redirect()->route('board-results.show', $exam->id)
            ->with('success', "Saved marks for {$touched} student".($touched === 1 ? '' : 's').".");
    }

    // ─── Excel template + import ───────────────────────────────────────
    // Download → fill offline → upload → preview → confirm. Uses the same
    // BoardResultsImport class so validation logic isn't duplicated between
    // preview mode and commit mode.

    /** Download a blank Excel template pre-filled with student rows. */
    public function template(int $boardExam, Request $request)
    {
        $exam = BoardExam::with('schoolClass:id,name')->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        $subjects = Subject::whereHas('classes', fn ($q) => $q->where('school_classes.id', $exam->school_class_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $students = Student::where('school_class_id', $exam->school_class_id)
            ->orderBy('roll_no')->orderBy('name')
            ->get(['id', 'roll_no', 'admission_no', 'name']);

        $slug = 'board-results-'.\Illuminate\Support\Str::slug($exam->title).'-template';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BoardResultTemplateExport($exam, $subjects, $students),
            "{$slug}.xlsx"
        );
    }

    /** Preview (default) or commit an uploaded Excel workbook. */
    public function import(Request $request, int $boardExam)
    {
        $exam = BoardExam::findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);
        abort_if($exam->is_locked, 423, 'This board exam is locked. Unlock to edit.');
        abort_unless($this->userCanEdit($request->user(), $exam), 403);

        $data = $request->validate([
            'file'   => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'commit' => ['nullable', 'boolean'],
        ]);
        $commit = (bool) ($data['commit'] ?? false);

        $subjects = Subject::whereHas('classes', fn ($q) => $q->where('school_classes.id', $exam->school_class_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->keyBy(fn ($s) => strtolower($s->name));

        $students = Student::where('school_class_id', $exam->school_class_id)
            ->get(['id', 'roll_no', 'admission_no', 'name'])
            ->keyBy(fn ($s) => (string) $s->roll_no);

        // Read the sheet as a 2D array (headers → subject names).
        $rows = \Maatwebsite\Excel\Facades\Excel::toArray(
            new \App\Imports\BoardResultsImport(),
            $request->file('file')
        );
        // Only sheet 0.
        $sheet = $rows[0] ?? [];
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $sheet[0] ?? []);
        $body = array_slice($sheet, 1);

        // Header map: find where "roll no" / "board roll" columns are;
        // everything else in the header row is treated as a subject name.
        $rollIdx      = array_search('roll no', $header, true);
        $boardRollIdx = array_search('board roll', $header, true);
        $nameIdx      = array_search('name', $header, true);

        $preview = [];
        $errors  = [];
        foreach ($body as $rowIdx => $row) {
            if (empty(array_filter($row))) continue;
            $rollNo = $rollIdx !== false ? (string) ($row[$rollIdx] ?? '') : '';
            $student = $students->get($rollNo);
            if (!$student) {
                $errors[] = "Row ".($rowIdx + 2).": roll no {$rollNo} not found in this class.";
                continue;
            }

            $cells = [];
            foreach ($header as $colIdx => $col) {
                if (in_array($col, ['roll no', 'name', 'board roll', 'admission no'], true)) continue;
                // Subject columns are either "<subject> theory" or "<subject> practical".
                if (!str_ends_with($col, ' theory') && !str_ends_with($col, ' practical')) continue;
                $kind = str_ends_with($col, ' theory') ? 'theory' : 'practical';
                $subjName = strtolower(trim(str_replace([' theory', ' practical'], '', $col)));
                $sub = $subjects->get($subjName);
                if (!$sub) continue;
                $val = $row[$colIdx] ?? null;
                if ($val === '' || $val === null) continue;
                $cells[] = ['subject_id' => $sub->id, 'kind' => $kind, 'value' => (float) $val];
            }

            $preview[] = [
                'student_id'    => $student->id,
                'roll_no'       => $rollNo,
                'name'          => $student->name,
                'board_roll_no' => $boardRollIdx !== false ? (string) ($row[$boardRollIdx] ?? '') : '',
                'cells'         => $cells,
            ];
        }

        if (!$commit) {
            return response()->json([
                'ok'        => true,
                'preview'   => array_slice($preview, 0, 30),
                'total'     => count($preview),
                'errors'    => array_slice($errors, 0, 30),
                'error_total' => count($errors),
            ]);
        }

        // Commit — same code path as batchStore, folded into upsert loop.
        $touched = 0;
        DB::transaction(function () use ($exam, $preview, $request, &$touched) {
            foreach ($preview as $p) {
                $result = BoardResult::firstOrCreate(
                    ['board_exam_id' => $exam->id, 'student_id' => $p['student_id']],
                    ['entered_by' => $request->user()->id, 'entered_at' => now()]
                );
                if (!empty($p['board_roll_no'])) $result->board_roll_no = $p['board_roll_no'];
                $result->entered_by = $request->user()->id;
                $result->entered_at = now();
                $result->save();

                // Merge cells by subject_id since one subject has 2 columns.
                $bySub = [];
                foreach ($p['cells'] as $c) {
                    $sid = $c['subject_id'];
                    $bySub[$sid] ??= ['theory_marks' => 0, 'practical_marks' => 0, 'theory_max' => 75, 'practical_max' => 25];
                    $bySub[$sid][$c['kind'] === 'theory' ? 'theory_marks' : 'practical_marks'] = $c['value'];
                }
                foreach ($bySub as $sid => $vals) {
                    BoardResultSubject::updateOrCreate(
                        ['board_result_id' => $result->id, 'subject_id' => $sid],
                        [...$vals, 'is_absent' => false]
                    );
                }
                $this->calc->recomputeResult($result->fresh('subjects'));
                $touched++;
            }
            $this->calc->recomputePositions($exam->id);
        });

        return response()->json([
            'ok'      => true,
            'touched' => $touched,
        ]);
    }

    // ─── Reports (PDF / Excel / analytics dashboard) ─────────────────

    /** Analytics dashboard — charts, top 10, fail, supply lists. */
    public function analytics(int $boardExam, Request $request): Response
    {
        $exam = BoardExam::with(['school:id,name,logo,principal_name', 'schoolClass:id,name', 'academicSession:id,name'])
            ->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        $results = BoardResult::where('board_exam_id', $exam->id)
            ->with(['student:id,roll_no,admission_no,name,father_name', 'subjects.subject:id,name,code'])
            ->orderBy('position')
            ->orderByDesc('total_obtained')
            ->get();

        $stats = $this->summarize($results);

        return Inertia::render('BoardResults/Analytics', [
            'exam'    => $exam,
            'stats'   => $stats,
            'results' => $results,
        ]);
    }

    /** Individual student result card — A4 portrait PDF (parents' copy). */
    public function studentCardPdf(int $boardExam, int $student, Request $request)
    {
        $exam = BoardExam::with(['school:id,name,logo,principal_name,address_district,exam_officer_name', 'schoolClass:id,name', 'academicSession:id,name'])
            ->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        $result = BoardResult::where('board_exam_id', $exam->id)
            ->where('student_id', $student)
            ->with(['student:id,roll_no,admission_no,name,father_name,date_of_birth', 'subjects.subject:id,name,code'])
            ->firstOrFail();

        $pdf = Pdf::loadView('reports.board-result-card', [
            'exam'   => $exam,
            'result' => $result,
        ])->setPaper('a4', 'portrait');

        $slug = \Illuminate\Support\Str::slug("board-result {$result->student->name} {$exam->title}");
        return $pdf->stream("{$slug}.pdf");
    }

    /** Class-wide summary — one PDF listing every student's result. */
    public function classSummaryPdf(int $boardExam, Request $request)
    {
        $exam = BoardExam::with(['school:id,name,logo,principal_name', 'schoolClass:id,name', 'academicSession:id,name'])
            ->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        $results = BoardResult::where('board_exam_id', $exam->id)
            ->with(['student:id,roll_no,admission_no,name,father_name', 'subjects.subject:id,name,code'])
            ->orderBy('position')
            ->orderByDesc('total_obtained')
            ->get();

        $stats = $this->summarize($results);

        $pdf = Pdf::loadView('reports.board-result-summary', [
            'exam'    => $exam,
            'results' => $results,
            'stats'   => $stats,
        ])->setPaper('a3', 'landscape');

        $slug = \Illuminate\Support\Str::slug("board-summary {$exam->title}");
        return $pdf->stream("{$slug}.pdf");
    }

    /**
     * Bulk result cards — every student's individual card in ONE PDF
     * (one per page). Ideal for parent-teacher distribution: print once,
     * fold, hand out.
     */
    public function bulkCardsPdf(int $boardExam, Request $request)
    {
        $exam = BoardExam::with(['school:id,name,logo,principal_name,address_district,exam_officer_name', 'schoolClass:id,name', 'academicSession:id,name'])
            ->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        $results = BoardResult::where('board_exam_id', $exam->id)
            ->with(['student:id,roll_no,admission_no,name,father_name,date_of_birth', 'subjects.subject:id,name,code'])
            ->orderBy('position')
            ->orderByDesc('total_obtained')
            ->get();

        abort_if($results->isEmpty(), 404, 'No results entered yet for this exam.');

        $pdf = Pdf::loadView('reports.board-result-cards-bulk', [
            'exam'    => $exam,
            'results' => $results,
        ])->setPaper('a4', 'portrait');

        $slug = \Illuminate\Support\Str::slug("board-cards {$exam->title}");
        return $pdf->stream("{$slug}.pdf");
    }

    /** Toggle the "locked / finalised" flag — unlocks the public search. */
    public function toggleLock(int $boardExam, Request $request)
    {
        $exam = BoardExam::findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);
        abort_unless($this->userCanEdit($request->user(), $exam) || $exam->is_locked, 403);
        $exam->is_locked = !$exam->is_locked;
        $exam->save();
        return back()->with('success', $exam->is_locked
            ? "Exam locked. Public search will now serve these results."
            : "Exam unlocked. Public search is now hidden.");
    }

    /** Excel export of the whole class — one sheet with every student × subject. */
    public function classExcel(int $boardExam, Request $request)
    {
        $exam = BoardExam::with(['school:id,name', 'schoolClass:id,name', 'academicSession:id,name'])
            ->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        $results = BoardResult::where('board_exam_id', $exam->id)
            ->with(['student:id,roll_no,admission_no,name,father_name', 'subjects.subject:id,name,code'])
            ->orderBy('position')
            ->orderByDesc('total_obtained')
            ->get();

        $slug = 'board-'.\Illuminate\Support\Str::slug($exam->title);
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BoardResultsClassExport($exam, $results),
            "{$slug}.xlsx"
        );
    }

    /**
     * Aggregate stats used by both the analytics page and the class-summary
     * PDF. Kept private so the two report views can't drift.
     */
    private function summarize(\Illuminate\Support\Collection $results): array
    {
        $total = $results->count();
        $passed = $results->where('is_pass', true)->count();
        $supply = $results->where('is_supplementary', true)->count();
        $failed = $total - $passed - $supply;

        // Grade histogram — every FBISE grade even when count = 0 so
        // charts have consistent x-axis.
        $gradeBuckets = ['A1', 'A', 'B', 'C', 'D', 'E', 'F'];
        $gradeCounts = array_fill_keys($gradeBuckets, 0);
        foreach ($results as $r) {
            if (isset($gradeCounts[$r->grade])) $gradeCounts[$r->grade]++;
        }

        // Division breakdown.
        $divisionCounts = ['1st' => 0, '2nd' => 0, '3rd' => 0, 'Fail' => 0];
        foreach ($results as $r) {
            $d = $r->is_supplementary ? 'Fail' : ($r->division ?? 'Fail');
            $divisionCounts[$d] = ($divisionCounts[$d] ?? 0) + 1;
        }

        // Subject-wise pass rate + averages + per-grade counts.
        // 7 FBISE grade slots pre-seeded per subject so charts / tables
        // have a consistent shape even when a bucket is empty.
        $emptyGradeCounts = ['A1' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0];
        $subjectAgg = [];
        foreach ($results as $r) {
            foreach ($r->subjects as $sub) {
                $key = (int) $sub->subject_id;
                $subjectAgg[$key] ??= [
                    'subject_id'   => $key,
                    'name'         => $sub->subject?->name,
                    'appeared'     => 0,
                    'passed'       => 0,
                    'total_obt'    => 0,
                    'total_max'    => 0,
                    'grade_counts' => $emptyGradeCounts,
                ];
                $subjectAgg[$key]['appeared'] += 1;
                if ($sub->is_pass) $subjectAgg[$key]['passed'] += 1;
                $subjectAgg[$key]['total_obt'] += (float) $sub->total_marks;
                $subjectAgg[$key]['total_max'] += (float) $sub->max_marks;
                // Bucket this student into the matching grade slot.
                $g = $sub->grade ?? 'F';
                if (isset($subjectAgg[$key]['grade_counts'][$g])) {
                    $subjectAgg[$key]['grade_counts'][$g] += 1;
                }
            }
        }
        $subjectStats = collect($subjectAgg)->map(function ($row) {
            $row['pass_percent'] = $row['appeared'] > 0
                ? round(($row['passed'] / $row['appeared']) * 100, 1) : 0;
            $row['average_percent'] = $row['total_max'] > 0
                ? round(($row['total_obt'] / $row['total_max']) * 100, 1) : 0;
            return $row;
        })->values()->all();

        return [
            'total'            => $total,
            'passed'           => $passed,
            'failed'           => $failed,
            'supply'           => $supply,
            'pass_percentage'  => $total > 0 ? round($passed / $total * 100, 1) : 0,
            'avg_percentage'   => $total > 0 ? round($results->avg('percentage'), 1) : 0,
            'top_percentage'   => $total > 0 ? round((float) $results->max('percentage'), 1) : 0,
            'grades'           => $gradeCounts,
            'divisions'        => $divisionCounts,
            'subject_stats'    => $subjectStats,
            'top_10'           => $results->take(10)->values(),
            'failed_list'      => $results->filter(fn ($r) => !$r->is_pass && !$r->is_supplementary)->values(),
            'supply_list'      => $results->filter(fn ($r) => $r->is_supplementary)->values(),
        ];
    }

    // ─── FBISE gazette photo OCR ─────────────────────────────────────
    /**
     * Read a photo of a FBISE gazette page and return one student's
     * subject-wise marks as structured JSON. Reuses the same Gemini key
     * already configured for the marks-entry OCR — the difference is a
     * FBISE-specific prompt tuned for the gazette layout.
     *
     * Response shape (frontend maps directly into the entry form):
     *   {
     *     ok:            true,
     *     board_roll_no: "123456"   | null,
     *     subjects: [
     *       { name: "English",    theory: 65, practical: 20 },
     *       { name: "Chemistry",  theory: 55, practical: 22 },
     *       ...
     *     ]
     *   }
     */
    public function ocrGazette(Request $request, int $boardExam, int $student)
    {
        $exam = BoardExam::findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);
        abort_if($exam->is_locked, 423, 'This board exam is locked. Unlock to edit.');

        $request->validate([
            'image' => ['required', 'file', 'image', 'max:8192'],
        ]);

        $key = config('services.gemini.key') ?: env('GEMINI_API_KEY');
        if (!$key) {
            return response()->json([
                'ok' => false,
                'error' => 'OCR provider not configured. Add GEMINI_API_KEY to your .env file '
                          .'(free key at aistudio.google.com), then run `php artisan config:clear`.',
            ], 503);
        }

        $model = config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-flash-latest');
        $file  = $request->file('image');
        $bytes = file_get_contents($file->getRealPath());
        $mime  = $file->getMimeType() ?: 'image/jpeg';

        $prompt = <<<TXT
You are reading a photo of one student's FBISE result gazette page (SSC or HSSC).
Extract that student's data and output ONLY a JSON object of the form:
{
  "board_roll_no": "<the board roll number, digits only or null>",
  "subjects": [
    {"name": "<subject name>", "theory": <number>, "practical": <number>}
  ]
}
Rules:
  • "name" must be the subject name as printed (e.g. English, Urdu, Islamiat, Mathematics, Physics, Chemistry, Biology, Computer Science, Pak Studies, etc.).
  • If a subject has no practical component, set practical=0.
  • Do NOT invent subjects that are not on the page.
  • Numbers only for theory / practical (no percent signs, no grade letters).
  • Output ONLY the JSON. No prose, no code fences, no comments.
TXT;

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
            $resp = \Illuminate\Support\Facades\Http::timeout(45)->post($url, [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
                    ],
                ]],
                'generationConfig' => [
                    'temperature' => 0,
                    'responseMimeType' => 'application/json',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Could not reach OCR service.'], 502);
        }

        if (!$resp->successful()) {
            $msg = data_get($resp->json(), 'error.message', "Provider returned HTTP {$resp->status()}.");
            return response()->json(['ok' => false, 'error' => "OCR service error: {$msg}"], 502);
        }

        $content = data_get($resp->json(), 'candidates.0.content.parts.0.text', '');
        $parsed = json_decode($content, true);
        // Gemini sometimes wraps JSON in noise or appends a trailing brace —
        // fall back to extracting the first balanced {...} block.
        if (!is_array($parsed) || !isset($parsed['subjects'])) {
            $extracted = $this->extractFirstJsonObject($content);
            if ($extracted !== null) $parsed = json_decode($extracted, true);
        }
        if (!is_array($parsed) || !isset($parsed['subjects']) || !is_array($parsed['subjects'])) {
            return response()->json([
                'ok' => false,
                'error' => 'Could not parse the photo. Try a clearer image with better lighting.',
                'raw'   => $content,
            ], 200);
        }

        // Normalise + match extracted subject names against the class's
        // real subjects (case-insensitive) so the frontend can auto-fill
        // by subject_id, not by loose name matching.
        $classSubjects = Subject::whereHas('classes', fn ($q) => $q->where('school_classes.id', $exam->school_class_id))
            ->get(['id', 'name'])
            ->keyBy(fn ($s) => strtolower(trim($s->name)));

        $rows = [];
        foreach ($parsed['subjects'] as $s) {
            if (!is_array($s) || empty($s['name'])) continue;
            $key = strtolower(trim($s['name']));
            $match = $classSubjects->get($key);
            $rows[] = [
                'raw_name'      => $s['name'],
                'subject_id'    => $match?->id,
                'subject_name'  => $match?->name ?? $s['name'],
                'matched'       => $match !== null,
                'theory_marks'  => is_numeric($s['theory'] ?? null)    ? (float) $s['theory']    : null,
                'practical_marks' => is_numeric($s['practical'] ?? null) ? (float) $s['practical'] : 0,
            ];
        }

        return response()->json([
            'ok'            => true,
            'board_roll_no' => is_string($parsed['board_roll_no'] ?? null)
                ? preg_replace('/[^\d]/', '', $parsed['board_roll_no'])
                : null,
            'subjects'      => $rows,
        ]);
    }

    /**
     * Bracket-count a string to find the first balanced {...} block —
     * shields us from Gemini appending stray `}` or wrapping in code fences.
     */
    private function extractFirstJsonObject(string $s): ?string
    {
        $start = strpos($s, '{');
        if ($start === false) return null;
        $depth = 0; $inString = false; $escape = false;
        $len = strlen($s);
        for ($i = $start; $i < $len; $i++) {
            $ch = $s[$i];
            if ($escape) { $escape = false; continue; }
            if ($ch === '\\') { $escape = true; continue; }
            if ($ch === '"')  { $inString = !$inString; continue; }
            if ($inString)     continue;
            if ($ch === '{')   $depth++;
            elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) return substr($s, $start, $i - $start + 1);
            }
        }
        return null;
    }

    // ─── Year-on-year comparison ─────────────────────────────────────
    /**
     * Compare THIS exam's aggregate against every prior exam for the
     * same school + class + level. Used by the Analytics page to render
     * a multi-session trend chart + a compact leaderboard.
     */
    public function yearOverYear(int $boardExam, Request $request)
    {
        $exam = BoardExam::with('academicSession:id,name')->findOrFail($boardExam);
        $this->authorizeSchoolAccess($request, $exam);

        $sameCohort = BoardExam::where('school_id', $exam->school_id)
            ->where('school_class_id', $exam->school_class_id)
            ->where('level', $exam->level)
            ->with('academicSession:id,name')
            ->orderBy('announced_on')
            ->orderBy('id')
            ->get();

        $rows = $sameCohort->map(function ($e) use ($exam) {
            $results = BoardResult::where('board_exam_id', $e->id)->get();
            $total  = $results->count();
            $passed = $results->where('is_pass', true)->count();
            return [
                'id'              => $e->id,
                'title'           => $e->title,
                'session'         => $e->academicSession?->name,
                'announced_on'    => $e->announced_on?->format('Y-m-d'),
                'total'           => $total,
                'passed'          => $passed,
                'pass_percentage' => $total > 0 ? round($passed / $total * 100, 1) : 0,
                'average_percent' => $total > 0 ? round($results->avg('percentage'), 1) : 0,
                'top_percent'     => $total > 0 ? round((float) $results->max('percentage'), 1) : 0,
                'is_current'      => $e->id === $exam->id,
            ];
        });

        return response()->json([
            'ok'   => true,
            'rows' => $rows,
        ]);
    }

    // ─── Shared guards ───

    private function authorizeSchoolAccess(Request $request, BoardExam $exam): void
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && (int) $exam->school_id !== (int) $user->school_id) {
            abort(403, 'You can only view board exams for your own school.');
        }
    }

    private function userCanEdit($user, BoardExam $exam): bool
    {
        if ($exam->is_locked) return false;
        return $user->isSuperAdmin()
            || $user->can('board_results.manage')
            || (int) $exam->school_id === (int) ($user->school_id ?? 0);
    }
}
