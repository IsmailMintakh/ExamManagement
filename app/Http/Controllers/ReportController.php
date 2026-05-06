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
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas('schools', fn ($q2) => $q2->where('schools.id', $user->school_id)))
            ->with('examType')
            ->withCount('results')
            ->latest()
            ->get();

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name']);

        return Inertia::render('Reports/Index', [
            'exams' => $exams,
            'classes' => $classes,
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
}
