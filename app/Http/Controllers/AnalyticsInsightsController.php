<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsInsightsController extends Controller
{
    /**
     * Year-over-Year student progress across exams/sessions.
     */
    public function studentProgress(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeInsights($user);

        $studentId = $request->integer('student_id');

        // No student selected — show picker
        if (!$studentId) {
            $students = $this->studentsForUser($user)
                ->with(['school:id,name', 'schoolClass:id,name', 'section:id,name'])
                ->orderBy('name')
                ->limit(500)
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'admission_no' => $s->admission_no,
                    'roll_no' => $s->roll_no,
                    // IDs are needed by the picker's school/class/section filters
                    // (string-name filtering is fragile when names repeat across schools)
                    'school_id' => $s->school_id,
                    'school_class_id' => $s->school_class_id,
                    'section_id' => $s->section_id,
                    'school_name' => $s->school?->name,
                    'class_name' => $s->schoolClass?->name,
                    'section_name' => $s->section?->name,
                ]);

            // Filter dropdowns — only schools the user can see
            $schools = $user->isSuperAdmin()
                ? School::active()->orderBy('name')->get(['id', 'name'])
                : ($user->school_id ? School::where('id', $user->school_id)->get(['id', 'name']) : collect());

            $classes = SchoolClass::query()
                ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
                ->active()->ordered()->get(['id', 'name', 'school_id']);

            $sections = Section::query()
                ->whereHas('schoolClass', function ($q) use ($user) {
                    $q->when(!$user->isSuperAdmin(), fn ($q2) => $q2->where('school_id', $user->school_id));
                })
                ->active()->orderBy('name')->get(['id', 'name', 'school_class_id']);

            return Inertia::render('Insights/StudentProgress', [
                'student' => null,
                'students' => $students,
                'schools' => $schools,
                'classes' => $classes,
                'sections' => $sections,
                'isSuperAdmin' => $user->isSuperAdmin(),
                'trend' => [],
                'subjectTrend' => [],
                'summary' => null,
            ]);
        }

        // Verify access
        $student = $this->studentsForUser($user)
            ->with(['school:id,name', 'schoolClass:id,name', 'section:id,name'])
            ->where('students.id', $studentId)
            ->firstOrFail();

        // All results for this student across sessions
        $results = Result::where('student_id', $student->id)
            ->with([
                'exam:id,name,start_date,academic_session_id',
                'exam.academicSession:id,name,start_date',
            ])
            ->orderBy('created_at')
            ->get();

        $trend = $results->map(function ($r) {
            return [
                'result_id' => $r->id,
                'exam_id' => $r->exam_id,
                'exam_name' => $r->exam?->name,
                'session_name' => $r->exam?->academicSession?->name,
                'exam_date' => $r->exam?->start_date?->format('Y-m-d'),
                'percentage' => (float) $r->percentage,
                'grade' => $r->grade,
                'position' => $r->position,
                'is_passed' => (bool) $r->is_passed,
                'total_marks' => (float) $r->total_marks,
                'obtained_marks' => (float) $r->obtained_marks,
            ];
        })->values();

        // Per-subject trend: build from stored subject_results on each Result
        $subjectBuckets = [];
        foreach ($results as $result) {
            $examLabel = $result->exam?->name ?? '—';
            foreach (($result->subject_results ?? []) as $sr) {
                $subjectName = $sr['subject_name'] ?? 'Unknown';
                $subjectId = $sr['subject_id'] ?? $subjectName;
                $pct = isset($sr['percentage']) ? (float) $sr['percentage'] : null;
                if ($pct === null && !empty($sr['total_marks']) && (float) $sr['total_marks'] > 0) {
                    $obtained = (float) ($sr['effective_marks'] ?? $sr['marks_obtained'] ?? 0);
                    $pct = round(($obtained / (float) $sr['total_marks']) * 100, 2);
                }
                if (!isset($subjectBuckets[$subjectId])) {
                    $subjectBuckets[$subjectId] = [
                        'subject_id' => $subjectId,
                        'subject_name' => $subjectName,
                        'dataPoints' => [],
                    ];
                }
                $subjectBuckets[$subjectId]['dataPoints'][] = [
                    'exam' => $examLabel,
                    'percentage' => $pct ?? 0,
                ];
            }
        }
        $subjectTrend = array_values($subjectBuckets);

        // Summary stats
        $summary = null;
        if ($trend->count() > 0) {
            $pcts = $trend->pluck('percentage')->map(fn ($v) => (float) $v);
            $best = $trend->sortByDesc('percentage')->first();
            $worst = $trend->sortBy('percentage')->first();
            $delta = $trend->count() >= 2
                ? round((float) $trend->last()['percentage'] - (float) $trend->first()['percentage'], 2)
                : 0;

            $summary = [
                'total_exams' => $trend->count(),
                'avg_percentage' => round($pcts->avg() ?? 0, 2),
                'best_exam' => $best ? ['exam_name' => $best['exam_name'], 'percentage' => $best['percentage']] : null,
                'worst_exam' => $worst ? ['exam_name' => $worst['exam_name'], 'percentage' => $worst['percentage']] : null,
                'improvement_delta' => $delta,
            ];
        }

        return Inertia::render('Insights/StudentProgress', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'school_name' => $student->school?->name,
                'class_name' => $student->schoolClass?->name,
                'section_name' => $student->section?->name,
            ],
            'students' => [],
            'trend' => $trend,
            'subjectTrend' => $subjectTrend,
            'summary' => $summary,
        ]);
    }

    /**
     * At-Risk students — smart alerts with severity & suggested actions.
     */
    public function atRiskStudents(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeInsights($user, allowClassTeacher: true);

        $currentSession = AcademicSession::currentSession();
        $sessionId = $currentSession?->id;

        // Scope students by role
        $studentsQuery = $this->studentsForUser($user);
        if ($sessionId) {
            $studentsQuery->where('students.academic_session_id', $sessionId);
        }
        $students = $studentsQuery
            ->with(['school:id,name', 'schoolClass:id,name', 'section:id,name'])
            ->limit(2000)
            ->get();

        // Pre-compute class averages for current session (per school_class_id, per exam_id)
        $classAvgRows = Result::query()
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->whereIn('student_id', $students->pluck('id'))
            ->select('school_class_id', 'exam_id', DB::raw('AVG(percentage) as avg_pct'))
            ->groupBy('school_class_id', 'exam_id')
            ->get();
        $classAvgMap = [];
        foreach ($classAvgRows as $row) {
            $classAvgMap[$row->school_class_id . '_' . $row->exam_id] = round((float) $row->avg_pct, 2);
        }

        $flagged = [];

        foreach ($students as $s) {
            // Fetch student's results in current session, ordered chronologically
            $sr = Result::where('student_id', $s->id)
                ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
                ->with('exam:id,name,start_date')
                ->orderBy('created_at')
                ->get();

            if ($sr->isEmpty()) continue;

            $latest = $sr->last();
            $last3 = $sr->slice(-3)->values();

            $reasons = [];
            $severity = 'low';

            // 1) Consistently failing — 2+ consecutive failures
            $consecFail = 0;
            $maxConsecFail = 0;
            foreach ($sr as $r) {
                if (!$r->is_passed) {
                    $consecFail++;
                    $maxConsecFail = max($maxConsecFail, $consecFail);
                } else {
                    $consecFail = 0;
                }
            }
            if ($maxConsecFail >= 2) {
                $reasons[] = [
                    'category' => 'consistently_failing',
                    'label' => 'Consistently failing',
                    'detail' => "Failed {$maxConsecFail} consecutive exams in this session.",
                    'suggested_action' => 'Arrange remedial classes and a parent-teacher meeting immediately.',
                ];
                $severity = $this->maxSeverity($severity, 'high');
            }

            // 2) Declining performance — >10% drop between consecutive exams
            for ($i = 1; $i < $sr->count(); $i++) {
                $prev = (float) $sr[$i - 1]->percentage;
                $curr = (float) $sr[$i]->percentage;
                if ($prev - $curr > 10) {
                    $reasons[] = [
                        'category' => 'declining',
                        'label' => 'Declining performance',
                        'detail' => 'Dropped ' . round($prev - $curr, 2) . '% from ' . ($sr[$i - 1]->exam?->name ?? 'prev') . ' to ' . ($sr[$i]->exam?->name ?? 'latest') . '.',
                        'suggested_action' => 'Investigate recent personal or academic issues; assign a mentor.',
                    ];
                    $severity = $this->maxSeverity($severity, 'medium');
                    break;
                }
            }

            // 3) Below class average — >15% below for latest exam
            $classKey = $s->school_class_id . '_' . $latest->exam_id;
            $classAvg = $classAvgMap[$classKey] ?? null;
            $latestPct = (float) $latest->percentage;
            if ($classAvg !== null && $classAvg - $latestPct > 15) {
                $reasons[] = [
                    'category' => 'below_class_avg',
                    'label' => 'Below class average',
                    'detail' => 'Latest exam ' . round($latestPct, 2) . '% is ' . round($classAvg - $latestPct, 2) . '% below class average (' . $classAvg . '%).',
                    'suggested_action' => 'Pair with a peer tutor and monitor weekly progress.',
                ];
                $severity = $this->maxSeverity($severity, 'medium');
            }

            // 4) Multiple subject failures — 3+ subjects failed in latest exam
            $failedSubs = (int) ($latest->subjects_failed ?? 0);
            if ($failedSubs >= 3) {
                $reasons[] = [
                    'category' => 'multiple_subject_failures',
                    'label' => 'Multiple subject failures',
                    'detail' => "Failed {$failedSubs} subjects in latest exam ({$latest->exam?->name}).",
                    'suggested_action' => 'Schedule targeted subject-wise intervention and counselling.',
                ];
                $severity = $this->maxSeverity($severity, 'high');
            }

            if (empty($reasons)) continue;

            $flagged[] = [
                'student_id' => $s->id,
                'student_name' => $s->name,
                'admission_no' => $s->admission_no,
                'roll_no' => $s->roll_no,
                'school_name' => $s->school?->name,
                'class_name' => $s->schoolClass?->name,
                'section_name' => $s->section?->name,
                'severity' => $severity,
                'reasons' => $reasons,
                'last_exam_percentage' => round($latestPct, 2),
                'class_average' => $classAvg,
                'last_three_exams' => $last3->map(fn ($r) => [
                    'exam_name' => $r->exam?->name,
                    'percentage' => round((float) $r->percentage, 2),
                    'is_passed' => (bool) $r->is_passed,
                ])->values(),
            ];
        }

        // Sort: high > medium > low, then by percentage ascending
        $severityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($flagged, function ($a, $b) use ($severityOrder) {
            $sa = $severityOrder[$a['severity']] ?? 3;
            $sb = $severityOrder[$b['severity']] ?? 3;
            if ($sa !== $sb) return $sa <=> $sb;
            return $a['last_exam_percentage'] <=> $b['last_exam_percentage'];
        });

        $counts = [
            'total' => count($flagged),
            'high' => count(array_filter($flagged, fn ($f) => $f['severity'] === 'high')),
            'medium' => count(array_filter($flagged, fn ($f) => $f['severity'] === 'medium')),
            'low' => count(array_filter($flagged, fn ($f) => $f['severity'] === 'low')),
        ];

        return Inertia::render('Insights/AtRisk', [
            'flagged' => $flagged,
            'counts' => $counts,
            'currentSession' => $currentSession,
        ]);
    }

    /**
     * Subject heatmap — student × subject matrix for a class+exam.
     */
    public function subjectHeatmap(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeInsights($user, allowClassTeacher: true);

        $classId = $request->integer('class_id');
        $examId = $request->integer('exam_id');

        // Classes user can see
        $classesQuery = SchoolClass::query()->active();
        if (!$user->isSuperAdmin()) {
            $classesQuery->where('school_id', $user->school_id);
        }
        $classes = $classesQuery->with('school:id,name')
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'school_name' => $c->school?->name,
            ]);

        // Exams dropdown (scope by session/class if possible)
        $exams = Exam::orderByDesc('start_date')
            ->limit(100)
            ->get(['id', 'name', 'start_date'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'start_date' => $e->start_date?->format('Y-m-d'),
            ]);

        if (!$classId || !$examId) {
            return Inertia::render('Insights/SubjectHeatmap', [
                'classes' => $classes,
                'exams' => $exams,
                'selectedClassId' => $classId,
                'selectedExamId' => $examId,
                'students' => [],
                'subjects' => [],
                'cells' => [],
                'averages' => [],
            ]);
        }

        // Validate access
        $class = SchoolClass::findOrFail($classId);
        if (!$user->isSuperAdmin() && $class->school_id !== $user->school_id) {
            abort(403, 'You cannot view this class.');
        }

        // Students for that class
        $studentsQuery = Student::where('school_class_id', $classId)
            ->where('status', 'active')
            ->with('section:id,name');
        if (!$user->isSuperAdmin()) {
            $studentsQuery->where('school_id', $user->school_id);
        }
        $studentModels = $studentsQuery->orderBy('roll_no')->orderBy('name')->get();
        $students = $studentModels->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'roll_no' => $s->roll_no,
            'admission_no' => $s->admission_no,
            'section_name' => $s->section?->name,
        ])->values();

        // Subjects for that exam+class
        $examSubjects = DB::table('exam_subjects')
            ->join('subjects', 'exam_subjects.subject_id', '=', 'subjects.id')
            ->where('exam_subjects.exam_id', $examId)
            ->where('exam_subjects.school_class_id', $classId)
            ->select(
                'exam_subjects.subject_id',
                'subjects.name',
                'subjects.code',
                'exam_subjects.total_marks',
                'exam_subjects.passing_marks'
            )
            ->orderBy('subjects.name')
            ->get();

        $subjects = $examSubjects->map(fn ($es) => [
            'id' => $es->subject_id,
            'name' => $es->name,
            'code' => $es->code,
            'total_marks' => (float) $es->total_marks,
            'passing_marks' => (float) $es->passing_marks,
        ])->values();

        // All marks for this exam & class & subjects
        $studentIds = $studentModels->pluck('id');
        $marks = Mark::where('exam_id', $examId)
            ->where('school_class_id', $classId)
            ->whereIn('student_id', $studentIds)
            ->get();

        // Index marks by student_id_subject_id
        $marksMap = [];
        foreach ($marks as $m) {
            $total = (float) $m->total_marks;
            $obtained = $m->is_absent ? 0 : ((float) $m->marks_obtained + (float) $m->grace_marks);
            $pct = $total > 0 ? round(($obtained / $total) * 100, 2) : 0;
            $marksMap[$m->student_id . '_' . $m->subject_id] = [
                'percentage' => $pct,
                'obtained' => $obtained,
                'total' => $total,
                'is_absent' => (bool) $m->is_absent,
            ];
        }

        // Build matrix: cells[studentIndex][subjectIndex] = { percentage, category, ... }
        $cells = [];
        $subjectTotals = array_fill(0, $subjects->count(), ['sum' => 0.0, 'count' => 0]);

        foreach ($students as $sIdx => $student) {
            $row = [];
            foreach ($subjects as $subIdx => $subject) {
                $key = $student['id'] . '_' . $subject['id'];
                $entry = $marksMap[$key] ?? null;
                if ($entry === null) {
                    $row[] = [
                        'percentage' => null,
                        'category' => 'no_data',
                        'is_absent' => false,
                    ];
                    continue;
                }
                if ($entry['is_absent']) {
                    $row[] = [
                        'percentage' => null,
                        'category' => 'absent',
                        'is_absent' => true,
                    ];
                    continue;
                }
                $pct = $entry['percentage'];
                $subjectTotals[$subIdx]['sum'] += $pct;
                $subjectTotals[$subIdx]['count']++;
                $row[] = [
                    'percentage' => $pct,
                    'category' => $this->categorizeCell($pct),
                    'is_absent' => false,
                    'obtained' => $entry['obtained'],
                    'total' => $entry['total'],
                ];
            }
            $cells[] = $row;
        }

        // Averages row
        $averages = [];
        foreach ($subjectTotals as $idx => $t) {
            $avg = $t['count'] > 0 ? round($t['sum'] / $t['count'], 2) : null;
            $averages[] = [
                'subject_id' => $subjects[$idx]['id'],
                'subject_name' => $subjects[$idx]['name'],
                'average' => $avg,
                'category' => $avg !== null ? $this->categorizeCell($avg) : 'no_data',
            ];
        }

        return Inertia::render('Insights/SubjectHeatmap', [
            'classes' => $classes,
            'exams' => $exams,
            'selectedClassId' => $classId,
            'selectedExamId' => $examId,
            'students' => $students,
            'subjects' => $subjects,
            'cells' => $cells,
            'averages' => $averages,
        ]);
    }

    /**
     * Teacher performance metrics.
     */
    public function teacherPerformance(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeInsights($user);

        $currentSession = AcademicSession::currentSession();
        $sessionId = $currentSession?->id;

        // SubjectTeacher assignments in scope
        $assignmentsQuery = SubjectTeacher::query()
            ->where('is_active', true)
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->with([
                'user:id,name,email,school_id',
                'subject:id,name,code',
                'schoolClass:id,name,school_id',
                'section:id,name,school_class_id',
            ]);

        if (!$user->isSuperAdmin()) {
            $assignmentsQuery->whereHas('user', fn ($q) => $q->where('school_id', $user->school_id));
        }

        $assignments = $assignmentsQuery->get();

        // School-wide subject averages (for delta comparison), keyed by subject_id
        $schoolAvgQuery = DB::table('marks')
            ->where('marks.status', 'submitted')
            ->when($sessionId, fn ($q) => $q->where('marks.academic_session_id', $sessionId))
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('marks.school_id', $user->school_id))
            ->selectRaw('subject_id, AVG(CASE WHEN total_marks > 0 THEN (marks_obtained + grace_marks) / total_marks * 100 ELSE 0 END) as avg_pct')
            ->groupBy('subject_id')
            ->get();
        $schoolSubjectAvg = [];
        foreach ($schoolAvgQuery as $row) {
            $schoolSubjectAvg[$row->subject_id] = round((float) $row->avg_pct, 2);
        }

        $teachers = [];
        foreach ($assignments as $a) {
            $marksQ = Mark::where('subject_id', $a->subject_id)
                ->where('school_class_id', $a->school_class_id)
                ->where('section_id', $a->section_id)
                ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId));

            $rows = $marksQ->get();
            $totalMarked = $rows->count();
            if ($totalMarked === 0) {
                $teachers[] = [
                    'assignment_id' => $a->id,
                    'teacher_id' => $a->user_id,
                    'teacher_name' => $a->user?->name,
                    'teacher_email' => $a->user?->email,
                    'subject_id' => $a->subject_id,
                    'subject_name' => $a->subject?->name,
                    'subject_code' => $a->subject?->code,
                    'class_name' => $a->schoolClass?->name,
                    'section_name' => $a->section?->name,
                    'total_marked' => 0,
                    'avg_marks' => 0,
                    'avg_percentage' => 0,
                    'pass_rate' => 0,
                    'school_avg' => $schoolSubjectAvg[$a->subject_id] ?? null,
                    'delta_vs_school' => null,
                ];
                continue;
            }

            $avgMarks = round((float) $rows->avg('marks_obtained'), 2);

            $sumPct = 0.0;
            $passed = 0;
            foreach ($rows as $m) {
                $t = (float) $m->total_marks;
                if ($t > 0) {
                    $obt = $m->is_absent ? 0 : ((float) $m->marks_obtained + (float) $m->grace_marks);
                    $pct = ($obt / $t) * 100;
                    $sumPct += $pct;
                    $passing = (float) ($m->total_marks * 0.33); // fallback threshold
                    // Consider passed if marks >= passing_marks on exam_subject
                    if (!$m->is_absent && $obt >= $passing) $passed++;
                }
            }
            $avgPct = round($sumPct / $totalMarked, 2);
            $passRate = round(($passed / $totalMarked) * 100, 2);
            $schoolAvg = $schoolSubjectAvg[$a->subject_id] ?? null;
            $delta = $schoolAvg !== null ? round($avgPct - $schoolAvg, 2) : null;

            $teachers[] = [
                'assignment_id' => $a->id,
                'teacher_id' => $a->user_id,
                'teacher_name' => $a->user?->name,
                'teacher_email' => $a->user?->email,
                'subject_id' => $a->subject_id,
                'subject_name' => $a->subject?->name,
                'subject_code' => $a->subject?->code,
                'class_name' => $a->schoolClass?->name,
                'section_name' => $a->section?->name,
                'total_marked' => $totalMarked,
                'avg_marks' => $avgMarks,
                'avg_percentage' => $avgPct,
                'pass_rate' => $passRate,
                'school_avg' => $schoolAvg,
                'delta_vs_school' => $delta,
            ];
        }

        usort($teachers, fn ($a, $b) => $b['pass_rate'] <=> $a['pass_rate']);

        $stats = [
            'total_teachers' => count(array_unique(array_column($teachers, 'teacher_id'))),
            'total_assignments' => count($teachers),
            'avg_pass_rate' => count($teachers) > 0
                ? round(array_sum(array_column($teachers, 'pass_rate')) / count($teachers), 2)
                : 0,
            'top_performer' => $teachers[0] ?? null,
        ];

        return Inertia::render('Insights/TeacherPerformance', [
            'teachers' => $teachers,
            'stats' => $stats,
            'currentSession' => $currentSession,
        ]);
    }

    // ---------- Helpers ----------

    protected function authorizeInsights(User $user, bool $allowClassTeacher = false): void
    {
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) return;
        if ($allowClassTeacher && $user->isClassTeacher()) return;
        abort(403, 'You do not have access to insights.');
    }

    /**
     * Build a base query for students visible to this user.
     */
    protected function studentsForUser(User $user)
    {
        $q = Student::query()->where('status', 'active');

        if ($user->isSuperAdmin()) {
            return $q;
        }
        if ($user->isSchoolAdmin()) {
            return $q->where('school_id', $user->school_id);
        }
        if ($user->isClassTeacher()) {
            $sectionIds = Section::where('class_teacher_id', $user->id)->pluck('id');
            return $q->whereIn('section_id', $sectionIds);
        }
        return $q->whereRaw('1=0');
    }

    protected function maxSeverity(string $current, string $candidate): string
    {
        $order = ['low' => 1, 'medium' => 2, 'high' => 3];
        return ($order[$candidate] ?? 0) > ($order[$current] ?? 0) ? $candidate : $current;
    }

    protected function categorizeCell(float $pct): string
    {
        if ($pct >= 80) return 'excellent';
        if ($pct >= 60) return 'good';
        if ($pct >= 40) return 'average';
        if ($pct >= 33) return 'weak';
        return 'fail';
    }
}
