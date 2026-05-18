<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\Result;
use App\Models\ResultCardTemplate;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('reports.view'), 403);
        $currentSession = AcademicSession::currentSession();

        $exams = Exam::query()
            ->whereIn('status', ['marks_entry', 'processing', 'completed', 'published'])
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->visibleToSchool($user->school_id))
            // Teachers: narrow to exams they actually teach in.
            ->forTeacher($user)
            ->with('examType')
            ->withCount('results')
            ->latest()
            ->get();

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name']);

        // Pickers for the new analytics reports.
        $sections = Section::query()
            ->when(!$user->isSuperAdmin(),
                fn ($q) => $q->whereHas('schoolClass', fn ($qq) => $qq->where('school_id', $user->school_id)))
            ->with('schoolClass:id,name')
            ->active()
            ->get(['id', 'name', 'school_class_id'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => trim(($s->schoolClass?->name ?? '') . ' — ' . $s->name),
            ])
            ->values();

        $students = Student::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()
            ->orderBy('name')
            ->limit(2000) // safety cap; admin can search via the picker
            ->get(['id', 'name', 'roll_no'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name . ($s->roll_no ? " (Roll {$s->roll_no})" : ''),
            ]);

        $teachers = \App\Models\User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher']))
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]);

        return Inertia::render('Reports/Index', [
            'exams' => $exams,
            'classes' => $classes,
            'sections' => $sections,
            'students' => $students,
            'teachers' => $teachers,
        ]);
    }

    public function awardList(int $exam)
    {
        $examModel = Exam::with(['examType', 'academicSession'])->findOrFail($exam);
        $user = request()->user();
        $school = $user->school ?? (object)['name' => 'All Schools'];
        $academicSession = $examModel->academicSession;

        $results = Result::where('exam_id', $exam)
            ->where('is_passed', true)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            // Class-teachers see only their assigned section(s) in the
            // award list — no cross-class peeking.
            ->when($user->isClassTeacher() && !$user->isSchoolAdmin() && !$user->isSuperAdmin(),
                fn ($q) => $q->whereIn('section_id', $user->classSections->pluck('id'))
            )
            ->with(['student', 'school', 'schoolClass', 'section'])
            ->orderByDesc('percentage')
            ->get();

        // Group by class name -> section name
        $classResults = $results->groupBy(fn ($r) => $r->schoolClass?->name ?? 'Unknown')
            ->map(fn ($group) => $group->groupBy(fn ($r) => $r->section?->name ?? 'Unknown'));

        $pdf = Pdf::loadView('reports.award-list', ['exam' => $examModel, 'school' => $school, 'academicSession' => $academicSession, 'classResults' => $classResults]);
        return $pdf->stream("award-list-{$examModel->slug}.pdf");
    }

    public function resultSheet(int $exam, int $schoolClass)
    {
        $examModel = Exam::with([
            'examType', 'gradingScale.entries', 'examController:id,name',
            'examSubjects' => function ($q) use ($schoolClass) {
                $q->where('school_class_id', $schoolClass)->with('subject');
            },
        ])->findOrFail($exam);

        $schoolClassModel = SchoolClass::with(['sections.classTeacher:id,name', 'school'])->findOrFail($schoolClass);

        $user = request()->user();
        if (!$user->isSuperAdmin() && $schoolClassModel->school_id !== $user->school_id) {
            abort(403, 'You can only view result sheets for your school.');
        }

        // Class-teachers can only print result sheets for the class their
        // assigned section(s) belong to. Without this guard, a class-teacher
        // could URL-guess any class in their school.
        if ($user->isClassTeacher() && !$user->isSchoolAdmin() && !$user->isSuperAdmin()) {
            $teacherClassIds = $user->classSections->pluck('school_class_id')->unique();
            if (!$teacherClassIds->contains($schoolClass)) {
                abort(403, 'You can only view result sheets for your assigned class.');
            }
        }

        $results = Result::where('exam_id', $exam)
            ->where('school_class_id', $schoolClass)
            ->with(['student', 'section'])
            ->orderBy('section_id')
            ->orderBy('position')
            ->get();

        $summary = [
            'total' => $results->count(),
            'passed' => $results->where('is_passed', true)->count(),
            'failed' => $results->where('is_passed', false)->count(),
            'passPercentage' => $results->count() > 0
                ? round($results->where('is_passed', true)->count() / $results->count() * 100, 2)
                : 0,
            'highestPercentage' => $results->max('percentage'),
            'lowestPercentage' => $results->min('percentage'),
            'averagePercentage' => round($results->avg('percentage'), 2),
        ];

        $school = $schoolClassModel->school;
        $academicSession = $examModel->academicSession;

        // Build subjects list for table header
        $subjects = $examModel->examSubjects->map(fn ($es) => [
            'id' => $es->subject_id,
            'code' => $es->subject?->code ?? $es->subject?->name,
            'name' => $es->subject?->name,
            'total' => $es->total_marks,
        ])->toArray();

        // Attach per-subject marks to each result
        $results->each(function ($result) use ($exam) {
            $marks = Mark::where('exam_id', $exam)
                ->where('student_id', $result->student_id)
                ->get();
            $subjectResults = [];
            foreach ($marks as $mark) {
                $subjectResults[$mark->subject_id] = [
                    'obtained' => $mark->marks_obtained,
                    'total' => $mark->total_marks,
                    'is_absent' => $mark->is_absent,
                    'failed' => $mark->marks_obtained < ($mark->examSubject?->passing_marks ?? 0) && !$mark->is_absent,
                ];
            }
            $result->subject_results = $subjectResults;
        });

        $pdf = Pdf::loadView('reports.result-sheet', ['exam' => $examModel, 'school' => $school, 'schoolClass' => $schoolClassModel, 'academicSession' => $academicSession, 'results' => $results, 'summary' => $summary, 'subjects' => $subjects]);
        return $pdf->stream("result-sheet-{$examModel->slug}-{$schoolClassModel->slug}.pdf");
    }

    public function reportCard(int $exam, int $student)
    {
        $examModel = Exam::with(['examType', 'gradingScale.entries', 'academicSession'])->findOrFail($exam);
        $studentModel = Student::with(['school', 'schoolClass', 'section'])->findOrFail($student);

        $result = Result::where('exam_id', $exam)
            ->where('student_id', $student)
            ->firstOrFail();

        $marks = Mark::where('exam_id', $exam)
            ->where('student_id', $student)
            ->with(['subject', 'examSubject'])
            ->get();

        // Build subjectResults array from the stored JSON + attach grade to each subject
        $gradingEntries = $examModel->gradingScale?->entries ?? collect();
        $subjectResults = collect($result->subject_results ?? [])->map(function ($sr) use ($gradingEntries) {
            $pct = (float) ($sr['percentage'] ?? 0);
            $grade = '-';
            foreach ($gradingEntries as $entry) {
                if ($pct >= (float) $entry->min_percentage && $pct <= (float) $entry->max_percentage) {
                    $grade = $entry->grade;
                    break;
                }
            }
            return [
                'subject_name' => $sr['subject_name'] ?? '',
                'subject_code' => $sr['subject_code'] ?? '',
                'total_marks' => $sr['total_marks'] ?? 0,
                'passing_marks' => $sr['passing_marks'] ?? 0,
                'marks_obtained' => $sr['marks_obtained'] ?? 0,
                'obtained' => $sr['effective_marks'] ?? $sr['marks_obtained'] ?? 0,
                'grace_marks' => $sr['grace_marks'] ?? 0,
                'percentage' => $sr['percentage'] ?? 0,
                'grade' => $grade,
                'is_absent' => $sr['is_absent'] ?? false,
                'failed' => !($sr['is_passed'] ?? true),
            ];
        })->values()->toArray();

        $data = [
            'exam' => $examModel,
            'student' => $studentModel,
            'school' => $studentModel->school,
            'schoolClass' => $studentModel->schoolClass,
            'section' => $studentModel->section,
            'academicSession' => $examModel->academicSession,
            'result' => $result,
            'marks' => $marks,
            'subjectResults' => $subjectResults,
            'gradingEntries' => $gradingEntries,
        ];

        // Look for a custom template configured for this session/school
        $customTemplate = ResultCardTemplate::getDefaultForSession(
            $examModel->academic_session_id,
            $studentModel->school_id
        );

        if ($customTemplate) {
            $placeholderData = [
                'school_name' => $studentModel->school?->name ?? '',
                'school_address' => $studentModel->school?->address ?? '',
                'school_logo' => $studentModel->school?->logo ? public_path('storage/' . $studentModel->school->logo) : '',
                'student_name' => $studentModel->name ?? '',
                'student_admission_no' => $studentModel->admission_no ?? '',
                'student_roll_no' => $studentModel->roll_no ?? '',
                'student_father_name' => $studentModel->father_name ?? '',
                'student_dob' => $studentModel->date_of_birth ? \Carbon\Carbon::parse($studentModel->date_of_birth)->format('d-m-Y') : '',
                'class_name' => $studentModel->schoolClass?->name ?? '',
                'section_name' => $studentModel->section?->name ?? '',
                'academic_session' => $examModel->academicSession?->name ?? '',
                'exam_name' => $examModel->name ?? '',
                'exam_type' => $examModel->examType?->name ?? '',
                'total_marks' => $result->total_marks ?? '',
                'obtained_marks' => $result->obtained_marks ?? '',
                'percentage' => $result->percentage !== null ? number_format($result->percentage, 2) : '',
                'grade' => $result->grade ?? '',
                'position' => $result->position ?? '',
                'status' => $result->is_passed ? 'PASSED' : 'FAILED',
                'principal_signature' => $studentModel->school?->principal_signature ? public_path('storage/' . $studentModel->school->principal_signature) : '',
                'ddo_signature' => '',
                'date' => now()->format('d-m-Y'),
                'subjects_table_html' => $this->buildSubjectsTableHtml($marks),
            ];

            $renderedHtml = $this->renderCustomTemplate($customTemplate, $placeholderData);
            $pdf = Pdf::loadHTML($renderedHtml);
        } else {
            $pdf = Pdf::loadView('reports.report-card', $data);
        }

        if (request()->has('download')) {
            return $pdf->download("report-card-{$studentModel->admission_no}-{$examModel->slug}.pdf");
        }

        return $pdf->stream("report-card-{$studentModel->admission_no}-{$examModel->slug}.pdf");
    }

    /**
     * Replace simple {{placeholder}} tokens in a custom HTML template.
     */
    protected function renderCustomTemplate(\App\Models\ResultCardTemplate $template, array $data): string
    {
        $html = $template->html_template;
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $html = str_replace('{{' . $key . '}}', (string) $value, $html);
            }
        }
        // Special case: subjects table
        if (isset($data['subjects_table_html'])) {
            $html = str_replace('{{subjects_table}}', $data['subjects_table_html'], $html);
        }
        return $html;
    }

    /**
     * Build the subjects table HTML from a collection of marks.
     */
    protected function buildSubjectsTableHtml($marks): string
    {
        $rows = '';
        foreach ($marks as $mark) {
            $subjectName = $mark->subject?->name ?? '—';
            $total = $mark->total_marks ?? 0;
            $obtained = $mark->is_absent ? 'AB' : ($mark->marks_obtained ?? 0);
            $grade = $mark->grade ?? ($mark->examSubject?->grade ?? '');
            $rows .= '<tr>'
                . '<td style="padding:6px;border:1px solid #ccc;">' . htmlspecialchars($subjectName) . '</td>'
                . '<td style="padding:6px;border:1px solid #ccc;text-align:center;">' . $total . '</td>'
                . '<td style="padding:6px;border:1px solid #ccc;text-align:center;">' . $obtained . '</td>'
                . '<td style="padding:6px;border:1px solid #ccc;text-align:center;">' . htmlspecialchars($grade) . '</td>'
                . '</tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;margin:10px 0;font-size:11px;">'
            . '<thead><tr style="background:#1a365d;color:#fff;">'
            . '<th style="padding:6px;border:1px solid #ccc;text-align:left;">Subject</th>'
            . '<th style="padding:6px;border:1px solid #ccc;">Total</th>'
            . '<th style="padding:6px;border:1px solid #ccc;">Obtained</th>'
            . '<th style="padding:6px;border:1px solid #ccc;">Grade</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    public function progressReport(int $student)
    {
        return redirect()->route('reports.index');
    }

    /**
     * Generate all mark sheets for a section in a single multi-page PDF.
     */
    public function sectionMarkSheets(int $exam, int $section)
    {
        $examModel = Exam::with(['examType', 'gradingScale.entries', 'academicSession', 'examController:id,name'])->findOrFail($exam);
        $sectionModel = Section::with(['schoolClass.school', 'classTeacher:id,name'])->findOrFail($section);

        $user = request()->user();
        if (!$user->isSuperAdmin() && $sectionModel->schoolClass->school_id !== $user->school_id) {
            abort(403, 'You can only download mark sheets for your school.');
        }

        $gradingEntries = $examModel->gradingScale?->entries ?? collect();

        $results = Result::where('exam_id', $exam)
            ->where('section_id', $section)
            ->with(['student'])
            ->orderBy('position')
            ->get();

        if ($results->isEmpty()) {
            return redirect()->back()->with('error', 'No results generated for this section yet.');
        }

        // Build students array with their results + subjectResults
        $sheets = $results->map(function ($result) use ($gradingEntries) {
            $subjectResults = collect($result->subject_results ?? [])->map(function ($sr) use ($gradingEntries) {
                $pct = (float) ($sr['percentage'] ?? 0);
                $grade = '-';
                foreach ($gradingEntries as $entry) {
                    if ($pct >= (float) $entry->min_percentage && $pct <= (float) $entry->max_percentage) {
                        $grade = $entry->grade;
                        break;
                    }
                }
                return [
                    'subject_name' => $sr['subject_name'] ?? '',
                    'subject_code' => $sr['subject_code'] ?? '',
                    'total_marks' => $sr['total_marks'] ?? 0,
                    'passing_marks' => $sr['passing_marks'] ?? 0,
                    'obtained' => $sr['effective_marks'] ?? $sr['marks_obtained'] ?? 0,
                    'grace_marks' => $sr['grace_marks'] ?? 0,
                    'percentage' => $sr['percentage'] ?? 0,
                    'grade' => $grade,
                    'is_absent' => $sr['is_absent'] ?? false,
                    'failed' => !($sr['is_passed'] ?? true),
                ];
            })->values()->toArray();

            return (object) [
                'result' => $result,
                'student' => $result->student,
                'subjectResults' => $subjectResults,
            ];
        });

        $pdf = Pdf::loadView('reports.section-mark-sheets', [
            'exam' => $examModel,
            'section' => $sectionModel,
            'schoolClass' => $sectionModel->schoolClass,
            'school' => $sectionModel->schoolClass->school,
            'academicSession' => $examModel->academicSession,
            'sheets' => $sheets,
            'gradingEntries' => $gradingEntries,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("mark-sheets-{$examModel->slug}-{$sectionModel->slug}.pdf");
    }

    /**
     * Bulk mark sheets for an entire exam — every section across every
     * class in a single multi-page PDF. Optional ?school_class_id= narrows
     * to one class (so a Principal can print "all sections of Class V").
     *
     * Same blade as the per-section variant; we just stack more student
     * sheets into the $sheets array.
     */
    public function bulkMarkSheets(Request $request, int $exam)
    {
        $examModel = Exam::with(['examType', 'gradingScale.entries', 'academicSession', 'examController:id,name'])->findOrFail($exam);
        $user = $request->user();

        $validated = $request->validate([
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
        ]);

        $resultsQ = Result::where('exam_id', $exam)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->when(!empty($validated['school_class_id']),
                fn ($q) => $q->where('school_class_id', $validated['school_class_id']))
            ->with(['student', 'schoolClass', 'section'])
            ->orderBy('school_class_id')
            ->orderBy('section_id')
            ->orderBy('position');

        // Class-teacher narrow — only their assigned section(s).
        if ($user->isClassTeacher() && !$user->isSchoolAdmin() && !$user->isSuperAdmin()) {
            $resultsQ->whereIn('section_id', $user->classSections->pluck('id'));
        }

        $results = $resultsQ->get();

        if ($results->isEmpty()) {
            return redirect()->back()->with('error', 'No generated results found in your scope.');
        }

        $gradingEntries = $examModel->gradingScale?->entries ?? collect();

        $sheets = $results->map(function ($result) use ($gradingEntries) {
            $subjectResults = collect($result->subject_results ?? [])->map(function ($sr) use ($gradingEntries) {
                $pct = (float) ($sr['percentage'] ?? 0);
                $grade = '-';
                foreach ($gradingEntries as $entry) {
                    if ($pct >= (float) $entry->min_percentage && $pct <= (float) $entry->max_percentage) {
                        $grade = $entry->grade;
                        break;
                    }
                }
                return [
                    'subject_name' => $sr['subject_name'] ?? '',
                    'subject_code' => $sr['subject_code'] ?? '',
                    'total_marks' => $sr['total_marks'] ?? 0,
                    'passing_marks' => $sr['passing_marks'] ?? 0,
                    'obtained' => $sr['effective_marks'] ?? $sr['marks_obtained'] ?? 0,
                    'grace_marks' => $sr['grace_marks'] ?? 0,
                    'percentage' => $sr['percentage'] ?? 0,
                    'grade' => $grade,
                    'is_absent' => $sr['is_absent'] ?? false,
                    'failed' => !($sr['is_passed'] ?? true),
                ];
            })->values()->toArray();

            return (object) [
                'result' => $result,
                'student' => $result->student,
                'subjectResults' => $subjectResults,
                // Per-sheet class/section so the blade can label each page
                // (the outer $section/$schoolClass don't apply in bulk mode).
                'schoolClass' => $result->schoolClass,
                'section' => $result->section,
            ];
        });

        // Use the first sheet's school for the header (all sheets are same
        // school in non-super-admin mode; super-admin won't typically print
        // bulk across schools anyway).
        $school = $results->first()->schoolClass?->school;

        $pdf = Pdf::loadView('reports.section-mark-sheets', [
            'exam' => $examModel,
            'section' => null,
            'schoolClass' => null,
            'school' => $school,
            'academicSession' => $examModel->academicSession,
            'sheets' => $sheets,
            'gradingEntries' => $gradingEntries,
        ])->setPaper('a4', 'portrait');

        $suffix = !empty($validated['school_class_id']) ? '-class-' . $validated['school_class_id'] : '-all';
        return $pdf->stream("mark-sheets-{$examModel->slug}{$suffix}.pdf");
    }

    /**
     * Result sheets for ALL classes in an exam, consolidated.
     *
     * Wraps the existing per-class blade and stacks them with page breaks
     * between. Hands a Principal one PDF instead of N separate downloads.
     */
    public function bulkResultSheets(Request $request, int $exam)
    {
        $examModel = Exam::with(['examType', 'gradingScale.entries', 'examController:id,name', 'examSubjects.subject'])
            ->findOrFail($exam);
        $user = $request->user();

        // Pull every class this exam touches in the user's school.
        $classIds = ExamSubject::where('exam_id', $exam)->pluck('school_class_id')->unique();
        $classes = SchoolClass::whereIn('id', $classIds)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with(['sections.classTeacher:id,name', 'school'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($classes->isEmpty()) {
            return redirect()->back()->with('error', 'No classes found for this exam in your scope.');
        }

        // Build a per-class block of results identical to the single-class
        // resultSheet shape, so the same blade can render multiple stacked.
        $blocks = $classes->map(function ($class) use ($examModel, $exam) {
            $results = Result::where('exam_id', $exam)
                ->where('school_class_id', $class->id)
                ->with(['student', 'section'])
                ->orderBy('section_id')
                ->orderBy('position')
                ->get();

            // Attach per-subject marks like resultSheet() does.
            $results->each(function ($r) use ($exam) {
                $marks = Mark::where('exam_id', $exam)->where('student_id', $r->student_id)->get();
                $bySubj = [];
                foreach ($marks as $m) {
                    $bySubj[$m->subject_id] = [
                        'obtained' => $m->marks_obtained,
                        'total' => $m->total_marks,
                        'is_absent' => $m->is_absent,
                        'failed' => $m->marks_obtained < ($m->examSubject?->passing_marks ?? 0) && !$m->is_absent,
                    ];
                }
                $r->subject_results = $bySubj;
            });

            $subjects = $examModel->examSubjects
                ->where('school_class_id', $class->id)
                ->map(fn ($es) => [
                    'id' => $es->subject_id,
                    'code' => $es->subject?->code ?? $es->subject?->name,
                    'name' => $es->subject?->name,
                    'total' => $es->total_marks,
                ])
                ->values()
                ->toArray();

            $passed = $results->where('is_passed', true)->count();
            $total = $results->count();
            $summary = [
                'total' => $total,
                'passed' => $passed,
                'failed' => $total - $passed,
                'passPercentage' => $total > 0 ? round($passed / $total * 100, 2) : 0,
                'highestPercentage' => $results->max('percentage'),
                'lowestPercentage' => $results->min('percentage'),
                'averagePercentage' => round($results->avg('percentage') ?? 0, 2),
            ];

            return (object) [
                'schoolClass' => $class,
                'results' => $results,
                'subjects' => $subjects,
                'summary' => $summary,
            ];
        });

        $school = $classes->first()?->school;

        $pdf = Pdf::loadView('reports.bulk-result-sheets', [
            'exam' => $examModel,
            'school' => $school,
            'academicSession' => $examModel->academicSession,
            'blocks' => $blocks,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("result-sheets-{$examModel->slug}-all-classes.pdf");
    }

    /**
     * Per-subject attendance signing sheet for an exam in a class.
     * One page per (section × subject) — invigilator carries one page to
     * each room and students sign next to their roll/name.
     */
    public function attendanceSheet(int $exam, int $schoolClass)
    {
        $examModel = Exam::with([
            'examType', 'academicSession',
            'examSubjects' => function ($q) use ($schoolClass) {
                $q->where('school_class_id', $schoolClass)->with('subject');
            },
        ])->findOrFail($exam);

        $schoolClassModel = SchoolClass::with(['school', 'sections' => function ($q) {
            $q->orderBy('name');
        }, 'sections.classTeacher:id,name'])->findOrFail($schoolClass);

        $user = request()->user();
        if (!$user->isSuperAdmin() && $schoolClassModel->school_id !== $user->school_id) {
            abort(403, 'You can only download attendance sheets for your school.');
        }

        $subjects = $examModel->examSubjects
            ->filter(fn ($es) => $es->subject !== null)
            ->map(fn ($es) => [
                'id' => $es->subject_id,
                'code' => $es->subject->code ?? null,
                'name' => $es->subject->name,
                'total' => $es->total_marks,
            ])->values();

        if ($subjects->isEmpty()) {
            return redirect()->back()->with('error', 'No subjects assigned to this class for the exam.');
        }

        // Pre-load students per section so the view doesn't N+1
        $sectionsWithStudents = $schoolClassModel->sections->map(function ($section) {
            $section->setRelation('studentsList', Student::query()
                ->where('section_id', $section->id)
                ->where('status', 'active')
                ->orderByRaw('CAST(roll_no AS UNSIGNED), roll_no')
                ->get(['id', 'roll_no', 'name', 'father_name']));
            return $section;
        });

        if ($sectionsWithStudents->sum(fn ($s) => $s->studentsList->count()) === 0) {
            return redirect()->back()->with('error', 'No active students found in this class.');
        }

        $pdf = Pdf::loadView('reports.attendance-sheet', [
            'exam' => $examModel,
            'school' => $schoolClassModel->school,
            'schoolClass' => $schoolClassModel,
            'sections' => $sectionsWithStudents,
            'subjects' => $subjects,
            'academicSession' => $examModel->academicSession,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("attendance-sheet-{$examModel->slug}-{$schoolClassModel->slug}.pdf");
    }

    public function exportExcel(string $type, int $exam)
    {
        $examModel = Exam::findOrFail($exam);
        $user = request()->user();

        switch ($type) {
            case 'results':
                $results = Result::where('exam_id', $exam)
                    ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
                    ->with(['student', 'school', 'schoolClass', 'section'])
                    ->orderBy('school_id')->orderBy('school_class_id')->orderBy('section_id')->orderBy('position')
                    ->get();

                return (new \App\Exports\ResultsExport($results, $examModel))
                    ->download("results-{$examModel->slug}.csv");

            case 'marks':
                $marks = Mark::where('exam_id', $exam)
                    ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
                    ->with(['student', 'subject', 'section', 'schoolClass'])
                    ->orderBy('school_class_id')->orderBy('section_id')->orderBy('subject_id')
                    ->get();

                return (new \App\Exports\MarksExport($marks, $examModel))
                    ->download("marks-{$examModel->slug}.csv");

            case 'award-list':
                $results = Result::where('exam_id', $exam)
                    ->where('is_passed', true)
                    ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
                    ->with(['student', 'school', 'schoolClass', 'section'])
                    ->orderByDesc('percentage')->limit(50)
                    ->get();

                return (new \App\Exports\AwardListExport($results, $examModel))
                    ->download("award-list-{$examModel->slug}.csv");

            default:
                return redirect()->back()->with('error', 'Invalid export type.');
        }
    }

    /**
     * GET /reports/exam-analytics/{exam} — drill-down dashboard for one exam.
     *
     * Aggregates pass rate / avg percentage / grade distribution by class,
     * subject, and (for super-admin) school. Provides the data the DDO and
     * Principal need to spot weak spots after every exam cycle.
     */
    public function examAnalytics(int $exam): Response
    {
        $examModel = Exam::with(['examType', 'academicSession'])->findOrFail($exam);
        $user = request()->user();
        abort_unless($user->can('reports.view'), 403);

        $results = Result::query()
            ->where('exam_id', $exam)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with([
                'school:id,name',
                'schoolClass:id,name',
                'section:id,name',
                'student:id,name,roll_no',
            ])
            ->get();

        // ── Headline numbers ──
        $total = $results->count();
        $passed = $results->where('is_passed', true)->count();
        $failed = $total - $passed;
        $avgPercentage = $total > 0 ? round($results->avg('percentage'), 2) : 0;
        $passRate = $total > 0 ? round($passed / $total * 100, 1) : 0;

        // ── Per-school breakdown (super-admin only) ──
        $bySchool = [];
        if ($user->isSuperAdmin()) {
            $bySchool = $results->groupBy('school_id')
                ->map(function ($rows, $schoolId) {
                    $count = $rows->count();
                    $p = $rows->where('is_passed', true)->count();
                    return [
                        'school_id' => (int) $schoolId,
                        'school_name' => $rows->first()->school?->name,
                        'students' => $count,
                        'passed' => $p,
                        'failed' => $count - $p,
                        'pass_rate' => $count ? round($p / $count * 100, 1) : 0,
                        'avg_percentage' => $count ? round($rows->avg('percentage'), 2) : 0,
                        'top_score' => $count ? round($rows->max('percentage'), 2) : 0,
                    ];
                })
                ->values()
                ->sortByDesc('pass_rate')
                ->values()
                ->all();
        }

        // ── Per-class breakdown ──
        $byClass = $results->groupBy('school_class_id')
            ->map(function ($rows, $classId) {
                $count = $rows->count();
                $p = $rows->where('is_passed', true)->count();
                return [
                    'class_id' => (int) $classId,
                    'class_name' => $rows->first()->schoolClass?->name,
                    'students' => $count,
                    'passed' => $p,
                    'pass_rate' => $count ? round($p / $count * 100, 1) : 0,
                    'avg_percentage' => $count ? round($rows->avg('percentage'), 2) : 0,
                ];
            })
            ->values()
            ->sortBy('class_name')
            ->values()
            ->all();

        // ── Per-subject breakdown (drill into subject_results JSON) ──
        $subjectStats = [];
        foreach ($results as $r) {
            foreach ((array) $r->subject_results as $sr) {
                if (empty($sr['subject_id'])) continue;
                $sid = $sr['subject_id'];
                if (!isset($subjectStats[$sid])) {
                    $subjectStats[$sid] = [
                        'subject_id' => $sid,
                        'subject_name' => $sr['subject_name'] ?? '—',
                        'attempted' => 0,
                        'passed' => 0,
                        'absent' => 0,
                        'sum_marks' => 0.0,
                        'sum_total' => 0.0,
                    ];
                }
                if (!empty($sr['is_absent'])) {
                    $subjectStats[$sid]['absent']++;
                    continue;
                }
                $subjectStats[$sid]['attempted']++;
                if (!empty($sr['is_passed'])) $subjectStats[$sid]['passed']++;
                $subjectStats[$sid]['sum_marks'] += (float) ($sr['effective_marks'] ?? $sr['marks_obtained'] ?? 0);
                $subjectStats[$sid]['sum_total'] += (float) ($sr['total_marks'] ?? 0);
            }
        }
        $bySubject = collect($subjectStats)->map(function ($s) {
            $att = max(1, $s['attempted']);
            $totalMarks = max(0.01, $s['sum_total']);
            return [
                'subject_id' => $s['subject_id'],
                'subject_name' => $s['subject_name'],
                'attempted' => $s['attempted'],
                'absent' => $s['absent'],
                'passed' => $s['passed'],
                'pass_rate' => $s['attempted']
                    ? round($s['passed'] / $att * 100, 1)
                    : 0,
                'avg_percentage' => $s['attempted']
                    ? round($s['sum_marks'] / $totalMarks * 100, 2)
                    : 0,
            ];
        })->values()->sortBy('subject_name')->values()->all();

        // ── Grade distribution ──
        $gradeDistribution = $results->groupBy('grade')
            ->map(fn ($rows) => $rows->count())
            ->sortKeys()
            ->all();

        // ── Top performers (top 10 across the result set) ──
        $top = $results->where('is_passed', true)
            ->sortByDesc('percentage')
            ->take(10)
            ->values()
            ->map(fn ($r) => [
                'student_name' => $r->student?->name,
                'roll_no' => $r->student?->roll_no,
                'class_name' => $r->schoolClass?->name,
                'section_name' => $r->section?->name,
                'school_name' => $r->school?->name,
                'percentage' => round($r->percentage, 2),
                'grade' => $r->grade,
                'position' => $r->position,
            ])
            ->all();

        return Inertia::render('Reports/ExamAnalytics', [
            'exam' => [
                'id' => $examModel->id,
                'name' => $examModel->name,
                'type' => $examModel->examType?->name,
                'session' => $examModel->academicSession?->name,
                'status' => $examModel->status,
            ],
            'headline' => [
                'total' => $total,
                'passed' => $passed,
                'failed' => $failed,
                'avg_percentage' => $avgPercentage,
                'pass_rate' => $passRate,
            ],
            'bySchool' => $bySchool,
            'byClass' => $byClass,
            'bySubject' => $bySubject,
            'gradeDistribution' => $gradeDistribution,
            'topPerformers' => $top,
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    /**
     * GET /reports/teacher-report-card/{user}
     *
     * Per-teacher performance: their subjects across sections, marks-entry
     * status, average percentage of students they teach. Useful for HR /
     * year-end review.
     */
    public function teacherReportCard(int $user): Response
    {
        $actor = request()->user();
        abort_unless($actor->can('reports.view') || $actor->id === $user, 403);

        $teacher = \App\Models\User::with('school')->findOrFail($user);
        if (!$actor->isSuperAdmin() && $actor->school_id !== $teacher->school_id) abort(403);

        $currentSession = AcademicSession::currentSession();

        $assignments = \App\Models\SubjectTeacher::query()
            ->where('user_id', $teacher->id)
            ->where('is_active', true)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->with(['subject:id,name,code', 'schoolClass:id,name', 'section:id,name'])
            ->get();

        $rows = $assignments->map(function ($a) use ($currentSession) {
            $marks = Mark::query()
                ->where('subject_id', $a->subject_id)
                ->where('section_id', $a->section_id)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->get();
            $entered = $marks->whereNotNull('marks_obtained')->count();
            $submitted = $marks->where('status', 'submitted')->count();
            $passed = $marks->filter(fn ($m) => $m->marks_obtained !== null && $m->total_marks > 0
                    && ($m->marks_obtained / $m->total_marks) >= 0.40)->count();
            $avgPct = $marks->whereNotNull('marks_obtained')->where('total_marks', '>', 0)
                ->avg(fn ($m) => $m->marks_obtained / $m->total_marks * 100);

            return [
                'subject_id' => $a->subject_id,
                'subject_name' => $a->subject?->name,
                'class_name' => $a->schoolClass?->name,
                'section_name' => $a->section?->name,
                'students_taught' => Student::where('section_id', $a->section_id)->active()->count(),
                'entries' => $entered,
                'submitted' => $submitted,
                'pass_count' => $passed,
                'pass_rate' => $marks->count() ? round($passed / $marks->count() * 100, 1) : null,
                'avg_percentage' => $avgPct ? round($avgPct, 2) : null,
            ];
        })->values()->all();

        $totals = [
            'subjects' => $assignments->pluck('subject_id')->unique()->count(),
            'sections' => $assignments->pluck('section_id')->unique()->count(),
            'students' => collect($rows)->sum('students_taught'),
            'avg_pass_rate' => collect($rows)->whereNotNull('pass_rate')->avg('pass_rate'),
        ];
        if ($totals['avg_pass_rate'] !== null) {
            $totals['avg_pass_rate'] = round($totals['avg_pass_rate'], 1);
        }

        return Inertia::render('Reports/TeacherReportCard', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'school' => $teacher->school?->name,
            ],
            'session' => $currentSession?->name,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    /**
     * GET /reports/progress-booklet/{student} — multi-page PDF for one student.
     *
     * Combines: cover, all session results so far, subject-wise trend,
     * remarks. Designed for handover at PTM (parent-teacher meeting).
     */
    public function progressBooklet(int $student)
    {
        $studentModel = Student::with(['school', 'schoolClass', 'section', 'academicSession'])
            ->findOrFail($student);
        $user = request()->user();
        abort_unless($user->can('reports.view'), 403);
        if (!$user->isSuperAdmin() && $user->school_id !== $studentModel->school_id) abort(403);

        $currentSession = AcademicSession::currentSession();

        $results = Result::query()
            ->where('student_id', $student)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->with(['exam.examType'])
            ->orderBy('finalized_at')
            ->orderBy('created_at')
            ->get();

        // Subject-wise trend across exams
        $subjectTrend = [];
        foreach ($results as $r) {
            foreach ((array) $r->subject_results as $sr) {
                $sid = $sr['subject_id'] ?? null;
                if (!$sid) continue;
                if (!isset($subjectTrend[$sid])) {
                    $subjectTrend[$sid] = [
                        'subject_name' => $sr['subject_name'] ?? '—',
                        'series' => [],
                    ];
                }
                $subjectTrend[$sid]['series'][] = [
                    'exam_name' => $r->exam?->name,
                    'percentage' => isset($sr['percentage']) ? round((float) $sr['percentage'], 1) : null,
                    'grade' => $sr['grade'] ?? null,
                    'is_absent' => (bool) ($sr['is_absent'] ?? false),
                ];
            }
        }

        $school = $studentModel->school;
        $pdf = Pdf::loadView('reports.progress-booklet', [
            'student' => $studentModel,
            'school' => $school,
            'session' => $currentSession,
            'results' => $results,
            'subjectTrend' => array_values($subjectTrend),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("progress-booklet-{$studentModel->admission_no}.pdf");
    }

    /** Bulk progress booklets for an entire section (one PDF, one section per student). */
    public function progressBookletBulk(int $section)
    {
        $sectionModel = Section::with('schoolClass.school')->findOrFail($section);
        $user = request()->user();
        abort_unless($user->can('reports.view'), 403);
        if (!$user->isSuperAdmin() && $user->school_id !== $sectionModel->schoolClass->school_id) abort(403);

        $currentSession = AcademicSession::currentSession();
        $students = Student::query()
            ->where('section_id', $section)
            ->active()
            ->orderBy('roll_no')
            ->get();

        // Pre-load all results in one query.
        $resultsByStudent = Result::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->with('exam.examType')
            ->get()
            ->groupBy('student_id');

        $pdf = Pdf::loadView('reports.progress-booklet-bulk', [
            'students' => $students,
            'school' => $sectionModel->schoolClass->school,
            'section' => $sectionModel,
            'session' => $currentSession,
            'resultsByStudent' => $resultsByStudent,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("progress-booklets-{$sectionModel->slug}.pdf");
    }
}
