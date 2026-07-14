<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\MarksSubmission;
use App\Models\Section;
use App\Models\SubjectTeacher;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marks-Entry Progress tracker for DDO + Principal.
 *
 * Two views in one page:
 *   - By teacher  → who has finished entering marks, who hasn't
 *   - By cell     → per (class, section, subject) status pill so the admin
 *                   can pin down exactly which papers are still pending
 *
 * "Cell" = one (exam, school_class, section, subject) tuple. Status:
 *   not_started  → no marks rows + no marks_submissions row
 *   in_progress  → some marks rows exist but no submitted submission
 *   submitted    → marks_submissions.status = submitted
 *   verified     → marks_submissions.status = verified
 */
class MarksProgressController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->isSchoolAdmin(), 403);

        $currentSession = AcademicSession::currentSession();
        $exams = Exam::query()
            ->whereIn('status', ['marks_entry', 'processing', 'completed'])
            // School-admin / principal sees only their own school's exams.
            // Super-admin (DDO) sees the full cross-school list.
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->visibleToSchool($user->school_id))
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status']);

        $examId = (int) ($request->input('exam_id') ?: ($exams->firstWhere('status', 'marks_entry')?->id ?? $exams->first()?->id));
        $exam = $examId ? Exam::find($examId) : null;

        if (!$exam) {
            return Inertia::render('Marks/Progress', [
                'exams' => $exams,
                'exam' => null,
                'cells' => [],
                'teachers' => [],
                'stats' => ['total' => 0, 'submitted' => 0, 'in_progress' => 0, 'not_started' => 0, 'pct' => 0],
                'filters' => $request->only(['exam_id', 'school_class_id']),
            ]);
        }

        // Pull all (subject × class) pairs for this exam, scoped by school
        // for principals. Each row becomes one or more "cells" — one per
        // section of that class.
        $examSubjects = ExamSubject::where('exam_id', $exam->id)
            ->with(['subject:id,name,code', 'schoolClass:id,name,school_id'])
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas(
                'schoolClass', fn ($x) => $x->where('school_id', $user->school_id)
            ))
            ->get();

        $classIds = $examSubjects->pluck('school_class_id')->unique();
        $sections = Section::query()
            ->whereIn('school_class_id', $classIds)
            ->where('is_active', true)
            ->orderBy('school_class_id')->orderBy('name')
            ->get(['id', 'school_class_id', 'name', 'class_teacher_id']);

        $submissions = MarksSubmission::where('exam_id', $exam->id)
            ->whereIn('section_id', $sections->pluck('id'))
            ->get()
            ->keyBy(fn ($s) => "{$s->subject_id}|{$s->section_id}");

        // Cell count (= one row per subject teacher needs to enter for).
        // Marks rows existing without a submission is "in_progress".
        $marksCount = \DB::table('marks')
            ->where('exam_id', $exam->id)
            ->whereIn('section_id', $sections->pluck('id'))
            ->selectRaw('subject_id, section_id, COUNT(*) as cnt')
            ->groupBy('subject_id', 'section_id')
            ->get()
            ->keyBy(fn ($r) => "{$r->subject_id}|{$r->section_id}");

        $teacherAssignments = SubjectTeacher::query()
            ->whereIn('school_class_id', $classIds)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->where('is_active', true)
            ->with('user:id,name')
            ->get();

        // Build cells.
        $cells = [];
        foreach ($examSubjects as $es) {
            $secsForClass = $sections->where('school_class_id', $es->school_class_id);
            foreach ($secsForClass as $sec) {
                // Respect per-subject "excluded sections" — a section
                // that doesn't take this paper shouldn't appear as a
                // pending marks cell to teachers or admins.
                if (!$es->appliesToSection((int) $sec->id)) continue;
                $key = "{$es->subject_id}|{$sec->id}";
                $sub = $submissions->get($key);
                $hasMarks = isset($marksCount[$key]);

                $status = 'not_started';
                if ($sub?->status === 'verified') $status = 'verified';
                elseif ($sub?->status === 'submitted') $status = 'submitted';
                elseif ($hasMarks) $status = 'in_progress';

                $assignedTeacher = $teacherAssignments
                    ->where('subject_id', $es->subject_id)
                    ->where('school_class_id', $es->school_class_id)
                    ->where('section_id', $sec->id)
                    ->first()
                    ?? $teacherAssignments
                        ->where('subject_id', $es->subject_id)
                        ->where('school_class_id', $es->school_class_id)
                        ->first();

                $cells[] = [
                    'subject_id' => $es->subject_id,
                    'subject' => $es->subject?->name,
                    'subject_code' => $es->subject?->code,
                    'class_id' => $es->school_class_id,
                    'class' => $es->schoolClass?->name,
                    'section_id' => $sec->id,
                    'section' => $sec->name,
                    'teacher' => $assignedTeacher?->user?->name,
                    'teacher_id' => $assignedTeacher?->user_id,
                    'status' => $status,
                    'submitted_at' => $sub?->submitted_at?->format('d M, h:i A'),
                    'submitted_by' => $sub ? optional(User::find($sub->submitted_by))->name : null,
                    'students' => isset($marksCount[$key]) ? (int) $marksCount[$key]->cnt : 0,
                ];
            }
        }

        // Optional class-filter applied last.
        $classFilter = $request->integer('school_class_id');
        if ($classFilter) {
            $cells = array_values(array_filter($cells, fn ($c) => $c['class_id'] === $classFilter));
        }

        // Per-teacher rollup.
        $teachersIndex = [];
        foreach ($cells as $c) {
            $tid = $c['teacher_id'] ?: 0;
            $key = $tid ?: 'unassigned';
            if (!isset($teachersIndex[$key])) {
                $teachersIndex[$key] = [
                    'teacher_id' => $tid ?: null,
                    'teacher' => $c['teacher'] ?? '— Unassigned —',
                    'cells' => [],
                    'submitted' => 0,
                    'in_progress' => 0,
                    'not_started' => 0,
                    'verified' => 0,
                    'total' => 0,
                ];
            }
            $teachersIndex[$key]['cells'][] = $c;
            $teachersIndex[$key]['total']++;
            $teachersIndex[$key][$c['status']]++;
        }
        // Sort: most overdue first (most "not_started"), then by name.
        $teachers = collect($teachersIndex)->sortBy([
            fn ($a, $b) => ($b['not_started'] + $b['in_progress']) <=> ($a['not_started'] + $a['in_progress']),
            fn ($a, $b) => strcmp((string) $a['teacher'], (string) $b['teacher']),
        ])->values()->all();

        $total = count($cells);
        $submitted = count(array_filter($cells, fn ($c) => in_array($c['status'], ['submitted', 'verified'], true)));
        $inProgress = count(array_filter($cells, fn ($c) => $c['status'] === 'in_progress'));
        $notStarted = count(array_filter($cells, fn ($c) => $c['status'] === 'not_started'));

        return Inertia::render('Marks/Progress', [
            'exams' => $exams,
            'exam' => ['id' => $exam->id, 'name' => $exam->name, 'status' => $exam->status],
            'cells' => $cells,
            'teachers' => $teachers,
            'classes' => $examSubjects->pluck('schoolClass')->filter()->unique('id')
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'stats' => [
                'total' => $total,
                'submitted' => $submitted,
                'in_progress' => $inProgress,
                'not_started' => $notStarted,
                'pct' => $total > 0 ? round(($submitted / $total) * 100) : 0,
            ],
            'filters' => $request->only(['exam_id', 'school_class_id']),
        ]);
    }
}
