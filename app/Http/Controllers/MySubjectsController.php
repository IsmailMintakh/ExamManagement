<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Mark;
use App\Models\Student;
use App\Models\SubjectTeacher;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Subjects" — a teacher's single-screen view of every subject they teach
 * in the current academic session, with the class, section, student count,
 * and marks-entry progress for the latest exam in each one. Designed so a
 * teacher can answer "what am I responsible for this term and where am I in
 * marks entry?" in one glance.
 */
class MySubjectsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        $assignments = SubjectTeacher::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->with([
                'subject:id,name,code,is_main',
                'schoolClass:id,name',
                'section:id,name,school_class_id',
                'academicSession:id,name',
            ])
            ->get();

        // Per-(section) student count so each row shows "X students".
        $sectionIds = $assignments->pluck('section_id')->unique()->filter();
        $studentCounts = Student::whereIn('section_id', $sectionIds)
            ->where('status', 'active')
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->selectRaw('section_id, COUNT(*) as cnt')
            ->groupBy('section_id')
            ->pluck('cnt', 'section_id');

        // Per-(subject, section) latest marks entry progress so the teacher
        // sees "12/30 entered" at a glance for the most recent exam they
        // need to act on.
        $marksCounts = Mark::query()
            ->whereIn('section_id', $sectionIds)
            ->whereIn('subject_id', $assignments->pluck('subject_id')->unique())
            ->selectRaw('subject_id, section_id, COUNT(*) as cnt')
            ->groupBy('subject_id', 'section_id')
            ->get()
            ->keyBy(fn ($r) => $r->subject_id.'-'.$r->section_id);

        $rows = $assignments->map(function ($a) use ($studentCounts, $marksCounts) {
            $students = (int) ($studentCounts[$a->section_id] ?? 0);
            $entered = (int) ($marksCounts->get($a->subject_id.'-'.$a->section_id)?->cnt ?? 0);
            return [
                'id' => $a->id,
                'subject_id' => $a->subject_id,
                'subject_name' => $a->subject?->name,
                'subject_code' => $a->subject?->code,
                'is_main_subject' => (bool) $a->subject?->is_main,
                'class_name' => $a->schoolClass?->name,
                'class_id' => $a->school_class_id,
                'section_name' => $a->section?->name,
                'section_id' => $a->section_id,
                'session_name' => $a->academicSession?->name,
                'student_count' => $students,
                'marks_entered' => $entered,
            ];
        })->values();

        return Inertia::render('MySubjects/Index', [
            'rows' => $rows,
            'currentSession' => $currentSession ? [
                'id' => $currentSession->id,
                'name' => $currentSession->name,
            ] : null,
            'totals' => [
                'assignments' => $rows->count(),
                'distinct_subjects' => $rows->pluck('subject_id')->unique()->count(),
                'distinct_sections' => $rows->pluck('section_id')->unique()->count(),
                'total_students' => (int) $rows->sum('student_count'),
            ],
        ]);
    }
}
