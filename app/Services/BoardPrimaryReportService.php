<?php

namespace App\Services;

use App\Models\AssessmentMark;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\Section;
use App\Models\Student;
use App\Models\SubjectTeacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the payload used by the board-primary term-wise result — the same
 * shape the controller previously assembled inline. Extracted here so the
 * single-section download and the multi-sheet "all primary sections" export
 * can share one code path with zero drift.
 *
 * Public API:
 *   $service->buildPayload(Exam $exam, Section $section, ?User $user): array
 */
class BoardPrimaryReportService
{
    /**
     * Assemble the board-primary payload for one section under one exam.
     *
     * @param  Exam        $exam    The exam the user is currently viewing —
     *                              anchors its own term slot.
     * @param  Section     $section The section whose students are on this sheet.
     * @param  User|null   $user    The currently-authenticated user; used only
     *                              for the "Prepared By" signature block. Pass
     *                              null when generating exports server-side.
     * @return array                Ready to hand to the blade view or Excel export.
     */
    public function buildPayload(Exam $exam, Section $section, ?User $user = null): array
    {
        // Eager-load the pieces the payload cares about.
        $exam->loadMissing(['academicSession', 'examController:id,name,signature_image']);
        $section->loadMissing(['schoolClass.school', 'classTeacher:id,name,signature_image']);

        $sectionId = (int) $section->id;
        $sessionId = $exam->academic_session_id;

        // ─── Resolve T-I / T-II / T-III exam ids ────────────────────────
        // The viewed exam anchors its own term slot; remaining slots pick
        // the most-recent exam of that term in the session.
        $termIdsMap = [];
        if (in_array($exam->term, ['first', 'second', 'final'], true)) {
            $termIdsMap[$exam->term] = $exam->id;
        }
        foreach (['first', 'second', 'final'] as $t) {
            if (isset($termIdsMap[$t])) continue;
            $other = Exam::where('academic_session_id', $sessionId)
                ->where('term', $t)
                ->where('id', '!=', $exam->id)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first(['id']);
            if ($other) $termIdsMap[$t] = $other->id;
        }
        $t1Id = $termIdsMap['first']  ?? null;
        $t2Id = $termIdsMap['second'] ?? null;
        $t3Id = $termIdsMap['final']  ?? null;
        $termIds = array_values(array_filter([$t1Id, $t2Id, $t3Id]));

        // Subjects the exam covers for THIS class, honouring section exclusions.
        $examSubjects = ExamSubject::where('exam_id', $exam->id)
            ->where('school_class_id', $section->school_class_id)
            ->with('subject:id,name,code')
            ->get()
            ->filter(fn ($es) => $es->appliesToSection($sectionId, (int) $section->school_class_id))
            ->values();

        // Every student in the section (no status filter — term-wise sheets
        // look back across the year).
        $students = Student::where('section_id', $sectionId)
            ->orderBy('roll_no')->orderBy('name')
            ->get(['id', 'roll_no', 'admission_no', 'name', 'father_name', 'date_of_birth', 'status']);

        // Bulk-load marks + assessments in two queries.
        $allMarks = Mark::whereIn('exam_id', $termIds ?: [0])
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['exam_id', 'student_id', 'subject_id', 'marks_obtained', 'is_absent'])
            ->groupBy(fn ($m) => $m->student_id.'|'.$m->subject_id.'|'.$m->exam_id);

        $assessmentByStudent = AssessmentMark::whereIn('student_id', $students->pluck('id'))
            ->where('academic_session_id', $sessionId)
            ->get()
            ->keyBy('student_id');

        // Section-level subject teachers — used as the fallback name.
        $subjectTeachers = SubjectTeacher::query()
            ->whereIn('subject_id', $examSubjects->pluck('subject_id'))
            ->where('school_class_id', $section->school_class_id)
            ->where('is_active', true)
            ->where(function ($q) use ($sectionId) {
                $q->where('section_id', $sectionId)->orWhereNull('section_id');
            })
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->with('user:id,name')
            ->get();
        $teacherBySubject = [];
        foreach ($subjectTeachers as $st) {
            $key = (int) $st->subject_id;
            $isSectionMatch = (int) $st->section_id === $sectionId;
            if (!isset($teacherBySubject[$key]) || $isSectionMatch) {
                $teacherBySubject[$key] = $st->user?->name;
            }
        }

        // ─── Per-term teacher map (per-exam teacher_id column) ──────────
        $termExamIdsAll = $termIds;
        $termExamSubjects = ExamSubject::query()
            ->whereIn('exam_id', $termExamIdsAll ?: [0])
            ->where('school_class_id', $section->school_class_id)
            ->whereIn('subject_id', $examSubjects->pluck('subject_id'))
            ->with('teacher:id,name')
            ->get()
            ->groupBy('exam_id');
        $termExamMap = ['t1' => $t1Id, 't2' => $t2Id, 't3' => $t3Id];
        $perTermTeachers = [];
        foreach ($examSubjects as $es) {
            $sid = (int) $es->subject_id;
            $perTermTeachers[$sid] = [];
            foreach ($termExamMap as $slot => $termExamId) {
                $name = null;
                if ($termExamId) {
                    $termES = ($termExamSubjects[$termExamId] ?? collect())
                        ->firstWhere('subject_id', $sid);
                    $name = $termES?->teacher?->name;
                }
                if ($name === null && $termExamId) {
                    $name = $teacherBySubject[$sid] ?? null;
                }
                $perTermTeachers[$sid][$slot] = $name;
            }
            $perTermTeachers[$sid]['total'] = null;
        }

        // ─── Board grade scale ──────────────────────────────────────────
        $gradeBands = [
            ['grade' => 'A++', 'min' => 95, 'max' => 100, 'label' => '95% To 100%'],
            ['grade' => 'A+',  'min' => 90, 'max' => 94,  'label' => '90% To 94%'],
            ['grade' => 'A',   'min' => 85, 'max' => 89,  'label' => '85% To 89%'],
            ['grade' => 'B++', 'min' => 80, 'max' => 84,  'label' => '80% To 84%'],
            ['grade' => 'B+',  'min' => 75, 'max' => 79,  'label' => '75% To 79%'],
            ['grade' => 'B',   'min' => 70, 'max' => 74,  'label' => '70% To 74%'],
            ['grade' => 'C',   'min' => 60, 'max' => 69,  'label' => '60% To 69%'],
            ['grade' => 'D',   'min' => 50, 'max' => 59,  'label' => '50% To 59%'],
            ['grade' => 'E',   'min' => 40, 'max' => 49,  'label' => '40% To 49%'],
            ['grade' => 'U',   'min' => 0,  'max' => 39,  'label' => 'Less than 40%'],
        ];
        $gradeFor = function ($pct) use ($gradeBands) {
            foreach ($gradeBands as $b) {
                if ($pct >= $b['min'] && $pct <= $b['max']) return $b['grade'];
            }
            return 'U';
        };

        // ─── Per-student rows ───────────────────────────────────────────
        $termsUsed = ['t1' => $t1Id, 't2' => $t2Id, 't3' => $t3Id];
        $termColTotal = 30;
        $rows = $students->map(function ($stu) use ($examSubjects, $allMarks, $termsUsed, $termColTotal, $assessmentByStudent, $gradeFor) {
            $subjects = [];
            $grandObtained = 0;
            $grandMax = 0;
            $appeared = false;
            foreach ($examSubjects as $es) {
                $sid = $es->subject_id;
                $t1 = $t2 = $t3 = null;
                foreach (['t1' => $termsUsed['t1'], 't2' => $termsUsed['t2'], 't3' => $termsUsed['t3']] as $slot => $eid) {
                    if (!$eid) continue;
                    $m = $allMarks->get($stu->id.'|'.$sid.'|'.$eid)?->first();
                    if ($m && !$m->is_absent) {
                        ${$slot} = (float) $m->marks_obtained;
                        $appeared = true;
                    }
                }
                $subTotal = ($t1 ?? 0) + ($t2 ?? 0) + ($t3 ?? 0);
                $subMax = $termColTotal * count(array_filter($termsUsed));
                $subjects[] = [
                    'subject_id' => $sid,
                    'code' => $es->subject?->code,
                    'name' => $es->subject?->name,
                    't1' => $t1,
                    't2' => $t2,
                    't3' => $t3,
                    'total' => $subTotal,
                ];
                $grandObtained += $subTotal;
                $grandMax += $subMax;
            }
            $am = $assessmentByStudent->get($stu->id);
            $coCurr = $am ? (float) $am->marks_obtained : null;
            $coMax = $am ? (float) $am->marks_total : 10;
            $totalWithCoCurr = $grandObtained + ($coCurr ?? 0);
            $maxWithCoCurr = $grandMax + $coMax;
            $pct = $maxWithCoCurr > 0 ? round(($totalWithCoCurr / $maxWithCoCurr) * 100, 2) : 0;
            return [
                'student_id' => $stu->id,
                'roll_no' => $stu->roll_no,
                'admission_no' => $stu->admission_no,
                'name' => $stu->name,
                'father_name' => $stu->father_name,
                'dob' => $stu->date_of_birth ? Carbon::parse($stu->date_of_birth)->format('d-M-Y') : '',
                'subjects' => $subjects,
                'co_curr' => $coCurr,
                'grand_obtained' => $totalWithCoCurr,
                'grand_max' => $maxWithCoCurr,
                'percentage' => $pct,
                'grade' => $appeared ? $gradeFor($pct) : 'U',
                'appeared' => $appeared,
                'remarks' => $appeared && $pct >= 40 ? 'Successful' : 'Un-Successful',
            ];
        })->values();

        // ─── Grade + summary counters ───────────────────────────────────
        $gradeCounts = array_fill_keys(array_column($gradeBands, 'grade'), 0);
        foreach ($rows as $r) { $gradeCounts[$r['grade']]++; }
        $appearedCount = $rows->where('appeared', true)->count();
        $passedCount = $rows->filter(fn ($r) => $r['appeared'] && $r['percentage'] >= 40)->count();
        $failedCount = $rows->count() - $passedCount;
        $avgPct = $rows->count() > 0 ? round($rows->avg('percentage'), 2) : 0;

        // ─── Per-subject × per-term stats ───────────────────────────────
        $subjectStats = [];
        $slots = ['t1', 't2', 't3', 'total'];
        foreach ($examSubjects as $es) {
            $sid = (int) $es->subject_id;
            $perSlot = [];
            foreach ($slots as $slot) {
                $obtained = [];
                $passedSlot = 0;
                $appearedSlot = 0;
                foreach ($rows as $row) {
                    $sr = collect($row['subjects'])->firstWhere('subject_id', $sid);
                    if (!$sr) continue;
                    $v = $sr[$slot] ?? null;
                    if ($v === null) continue;
                    $appearedSlot++;
                    $obtained[] = (float) $v;
                    $slotMax = $slot === 'total'
                        ? $termColTotal * count(array_filter($termsUsed))
                        : $termColTotal;
                    if ($slotMax > 0 && ($v / $slotMax) * 100 >= 40) $passedSlot++;
                }
                $sum = array_sum($obtained);
                $avg = count($obtained) > 0 ? round($sum / count($obtained), 1) : 0;
                $passPct = $appearedSlot > 0 ? round(($passedSlot / $appearedSlot) * 100, 1) : 0;
                $perSlot[$slot] = [
                    'total_obtained' => round($sum, 1),
                    'average'        => $avg,
                    'appeared'       => $appearedSlot,
                    'passed'         => $passedSlot,
                    'failed'         => max(0, $appearedSlot - $passedSlot),
                    'pass_percent'   => $passPct,
                ];
            }
            $subjectStats[$sid] = $perSlot;
        }
        $emptySlotStats = [
            't1'    => ['total_obtained' => 0, 'average' => 0, 'appeared' => 0, 'passed' => 0, 'failed' => 0, 'pass_percent' => 0],
            't2'    => ['total_obtained' => 0, 'average' => 0, 'appeared' => 0, 'passed' => 0, 'failed' => 0, 'pass_percent' => 0],
            't3'    => ['total_obtained' => 0, 'average' => 0, 'appeared' => 0, 'passed' => 0, 'failed' => 0, 'pass_percent' => 0],
            'total' => ['total_obtained' => 0, 'average' => 0, 'appeared' => 0, 'passed' => 0, 'failed' => 0, 'pass_percent' => 0],
        ];

        return [
            'exam' => $exam,
            'school' => $section->schoolClass->school,
            'schoolClass' => $section->schoolClass,
            'section' => $section,
            'academicSession' => $exam->academicSession,
            'subjects' => $examSubjects->map(fn ($es) => [
                'id' => $es->subject_id,
                'name' => $es->subject?->name,
                'code' => $es->subject?->code,
                'teacher' => $teacherBySubject[(int) $es->subject_id] ?? '',
                'teachers' => $perTermTeachers[(int) $es->subject_id] ?? [],
                'stats' => $subjectStats[(int) $es->subject_id] ?? $emptySlotStats,
            ])->values(),
            'rows' => $rows,
            'gradeBands' => $gradeBands,
            'gradeCounts' => $gradeCounts,
            'termCount' => count(array_filter($termsUsed)),
            'termColTotal' => $termColTotal,
            'summary' => [
                'total_students' => $rows->count(),
                'appeared' => $appearedCount,
                'passed' => $passedCount,
                'failed' => $failedCount,
                'pass_percentage' => $appearedCount > 0 ? round($passedCount / $appearedCount * 100, 2) : 0,
                'average_percentage' => $avgPct,
            ],
            'classTeacher' => $section->classTeacher?->name,
            'signatures' => [
                'prepared_by' => [
                    'name' => $user?->name,
                    'path' => $user?->signaturePath(),
                ],
                'class_teacher' => [
                    'name' => $section->classTeacher?->name,
                    'path' => $section->classTeacher?->signaturePath(),
                ],
                'hm_ddo' => [
                    'name' => $section->schoolClass->school?->principal_name,
                    'path' => $section->schoolClass->school?->resolveAssetPath('principal_signature'),
                ],
                'counter_signed' => [
                    'name' => $exam->examController?->name
                        ?? $section->schoolClass->school?->exam_officer_name,
                    'path' => $exam->examController?->signaturePath()
                        ?? $section->schoolClass->school?->resolveAssetPath('exam_officer_signature'),
                ],
            ],
        ];
    }

    /**
     * Return every PRIMARY-stage section that has at least one exam-subject
     * row for this exam. That is: sections whose students should appear on
     * the multi-sheet Excel export.
     *
     * Ordered by class sort_order, then section name — matches how tabs
     * read left-to-right in the workbook.
     */
    public function primarySectionsForExam(Exam $exam): Collection
    {
        // Class ids covered by this exam's subjects (some exams don't apply
        // to every class in the school).
        $classIds = ExamSubject::where('exam_id', $exam->id)
            ->pluck('school_class_id')
            ->unique()
            ->values();

        if ($classIds->isEmpty()) return collect();

        return Section::with(['schoolClass'])
            ->whereIn('school_class_id', $classIds)
            ->whereHas('schoolClass', fn ($q) => $q->whereIn('stage', ['pre_primary', 'primary']))
            ->get()
            ->sortBy(fn ($s) => [$s->schoolClass?->sort_order ?? 999, $s->schoolClass?->name, $s->name])
            ->values();
    }
}
