<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Danger Zone — destructive data-cleanup tools for the main admin (DDO /
 * super-admin) only. Used to wipe demo / test data so the school can start
 * clean, while keeping setup intact (students, teachers, classes, timetable
 * structure, etc.).
 *
 * Every action requires the admin's own password to confirm.
 *
 * Scope:
 *   - school_id = null  → wipe across all schools (truncate the tables)
 *   - school_id = N     → only delete rows tied to that school (keep parent
 *                          exam rows that are still attached to other schools)
 *
 * Setup tables (students, users, classes, sections, subject_teachers,
 * time_slots, academic_sessions, schools, site settings) are NEVER touched.
 */
class DataCleanupController extends Controller
{
    /** Tables touched when wiping ALL exam data (no school filter). */
    private const EXAM_TABLES = [
        'result_amendments',
        'result_submissions',
        'results',
        'marks_submissions',
        'marks',
        'exam_invigilators',
        'exam_seats',
        'exam_schedules',
        'exam_subjects',
        'exam_schools',
        'exams',
    ];

    /** Tables touched when wiping the weekly routine. `time_slots` is setup, kept. */
    private const TIMETABLE_TABLES = [
        'substitution_assignments',
        'teacher_absences',
        'timetable_entries',
    ];

    /** Super-admin only — Laravel 12 dropped constructor middleware. */
    private function guard(): void
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403, 'Only the main admin can access data cleanup.');
    }

    public function index(Request $request): Response
    {
        $this->guard();

        $schoolId = $request->integer('school_id') ?: null;

        $schools = School::orderBy('name')->get(['id', 'name', 'code']);

        $allTables = array_merge(self::EXAM_TABLES, self::TIMETABLE_TABLES);
        $counts = [];
        foreach ($allTables as $t) {
            $counts[$t] = $this->countScoped($t, $schoolId);
        }

        $exams = Exam::query()
            ->with(['examType:id,name', 'academicSession:id,name'])
            ->withCount(['examSubjects', 'marks', 'results'])
            ->when($schoolId, function ($q) use ($schoolId) {
                // Show exams that touch this school: either applies-to-all OR
                // attached via the exam_schools pivot OR has marks/results
                // recorded for this school.
                $q->where(function ($w) use ($schoolId) {
                    $w->where('apply_to_all_schools', true)
                      ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('exam_schools')
                          ->whereColumn('exam_schools.exam_id', 'exams.id')
                          ->where('exam_schools.school_id', $schoolId))
                      ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('marks')
                          ->whereColumn('marks.exam_id', 'exams.id')
                          ->where('marks.school_id', $schoolId));
                });
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'status' => $e->status,
                'is_locked' => (bool) $e->is_locked,
                'exam_type' => $e->examType?->name,
                'session' => $e->academicSession?->name,
                'start_date' => $e->start_date?->toDateString(),
                'end_date' => $e->end_date?->toDateString(),
                'exam_subjects_count' => $e->exam_subjects_count,
                'marks_count' => $e->marks_count,
                'results_count' => $e->results_count,
            ]);

        return Inertia::render('Admin/DataCleanup', [
            'counts' => $counts,
            'exams' => $exams,
            'schools' => $schools,
            'selectedSchoolId' => $schoolId,
        ]);
    }

    // ─────────────────────────── actions ───────────────────────────

    public function destroyExam(Request $request, Exam $exam): RedirectResponse
    {
        $this->guard();
        $v = $request->validate([
            'password' => ['required', 'current_password'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);

        $schoolId = $v['school_id'] ?? null;
        $deleted = $this->wipeForExam($exam->id, $schoolId);

        // Only drop the exam row when wiping across all schools. School-scoped
        // delete keeps the parent so other schools' data isn't disrupted.
        if (!$schoolId) {
            $exam->forceDelete();
        }

        $msg = $schoolId
            ? "Exam “{$exam->name}” data cleared for the selected school"
            : "Exam “{$exam->name}” deleted";

        return redirect($this->redirectUrl($schoolId))
            ->with('success', "{$msg} ({$this->fmtDeleted($deleted)}).");
    }

    public function wipeAllExams(Request $request): RedirectResponse
    {
        $this->guard();
        $v = $this->validateWipe($request);
        $schoolId = $v['school_id'] ?? null;

        $deleted = $schoolId
            ? $this->wipeExamScopedToSchool($schoolId)
            : $this->truncateTables(self::EXAM_TABLES);

        return redirect($this->redirectUrl($schoolId))
            ->with('success', $this->successLabel('All exams', $schoolId, $deleted));
    }

    public function wipeAllResults(Request $request): RedirectResponse
    {
        $this->guard();
        $v = $this->validateWipe($request);
        $schoolId = $v['school_id'] ?? null;

        if ($schoolId) {
            $resultIds = DB::table('results')->where('school_id', $schoolId)->pluck('id');
            $deleted = [
                'result_amendments' => $resultIds->isEmpty() ? 0
                    : DB::table('result_amendments')->whereIn('result_id', $resultIds)->delete(),
                'result_submissions' => DB::table('result_submissions')->where('school_id', $schoolId)->delete(),
                'results' => DB::table('results')->where('school_id', $schoolId)->delete(),
            ];
        } else {
            $deleted = $this->truncateTables(['result_amendments', 'result_submissions', 'results']);
        }

        return redirect($this->redirectUrl($schoolId))
            ->with('success', $this->successLabel('All results', $schoolId, $deleted));
    }

    public function wipeAllMarks(Request $request): RedirectResponse
    {
        $this->guard();
        $v = $this->validateWipe($request);
        $schoolId = $v['school_id'] ?? null;

        if ($schoolId) {
            $deleted = [
                'marks_submissions' => DB::table('marks_submissions')->where('school_id', $schoolId)->delete(),
                'marks' => DB::table('marks')->where('school_id', $schoolId)->delete(),
            ];
        } else {
            $deleted = $this->truncateTables(['marks_submissions', 'marks']);
        }

        return redirect($this->redirectUrl($schoolId))
            ->with('success', $this->successLabel('All marks', $schoolId, $deleted));
    }

    public function wipeAllDatesheets(Request $request): RedirectResponse
    {
        $this->guard();
        $v = $this->validateWipe($request);
        $schoolId = $v['school_id'] ?? null;

        if ($schoolId) {
            $classIds = $this->classIdsForSchool($schoolId);
            $deleted = ['exam_schedules' => $classIds->isEmpty() ? 0
                : DB::table('exam_schedules')->whereIn('school_class_id', $classIds)->delete()];
        } else {
            $deleted = $this->truncateTables(['exam_schedules']);
        }

        return redirect($this->redirectUrl($schoolId))
            ->with('success', $this->successLabel('All date sheets', $schoolId, $deleted));
    }

    public function wipeAllTimetable(Request $request): RedirectResponse
    {
        $this->guard();
        $v = $this->validateWipe($request);
        $schoolId = $v['school_id'] ?? null;

        if ($schoolId) {
            $classIds = $this->classIdsForSchool($schoolId);
            // Substitution assignments: scoped via the underlying timetable
            // entry's class. teacher_absences: scoped via the absent user.
            $entryIds = $classIds->isEmpty() ? collect()
                : DB::table('timetable_entries')->whereIn('school_class_id', $classIds)->pluck('id');
            $userIds = DB::table('users')->where('school_id', $schoolId)->pluck('id');

            $deleted = [
                'substitution_assignments' => $entryIds->isEmpty() ? 0
                    : DB::table('substitution_assignments')->whereIn('timetable_entry_id', $entryIds)->delete(),
                'teacher_absences' => $userIds->isEmpty() ? 0
                    : DB::table('teacher_absences')->whereIn('user_id', $userIds)->delete(),
                'timetable_entries' => $entryIds->isEmpty() ? 0
                    : DB::table('timetable_entries')->whereIn('id', $entryIds)->delete(),
            ];
        } else {
            $deleted = $this->truncateTables(self::TIMETABLE_TABLES);
        }

        return redirect($this->redirectUrl($schoolId))
            ->with('success', "Timetable wiped ({$this->fmtDeleted($deleted)}). Bell schedule kept.");
    }

    // ────────────────────── internals ──────────────────────

    private function validateWipe(Request $request): array
    {
        return $request->validate([
            'password' => ['required', 'current_password'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);
    }

    private function redirectUrl(?int $schoolId): string
    {
        $url = route('admin.data-cleanup.index');
        return $schoolId ? $url.'?school_id='.$schoolId : $url;
    }

    private function successLabel(string $what, ?int $schoolId, array $deleted): string
    {
        $scope = $schoolId
            ? ' for the selected school'
            : '';
        return "{$what} wiped{$scope} ({$this->fmtDeleted($deleted)}).";
    }

    /** Count a table, optionally scoped to a school. Returns 0 when missing. */
    private function countScoped(string $table, ?int $schoolId): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }
        if (!$schoolId) {
            return DB::table($table)->count();
        }

        return match ($table) {
            'marks', 'marks_submissions', 'results', 'result_submissions'
                => DB::table($table)->where('school_id', $schoolId)->count(),
            'exam_schools'
                => DB::table('exam_schools')->where('school_id', $schoolId)->count(),
            'result_amendments' => DB::table('result_amendments')
                ->whereIn('result_id', DB::table('results')->where('school_id', $schoolId)->pluck('id'))
                ->count(),
            'exam_subjects', 'exam_schedules', 'timetable_entries' => DB::table($table)
                ->whereIn('school_class_id', $this->classIdsForSchool($schoolId))
                ->count(),
            'exam_invigilators' => DB::table('exam_invigilators')
                ->whereIn('exam_schedule_id', DB::table('exam_schedules')
                    ->whereIn('school_class_id', $this->classIdsForSchool($schoolId))->pluck('id'))
                ->count(),
            'exam_seats' => DB::table('exam_seats')
                ->whereIn('student_id', DB::table('students')->where('school_id', $schoolId)->pluck('id'))
                ->count(),
            'teacher_absences' => DB::table('teacher_absences')
                ->whereIn('user_id', DB::table('users')->where('school_id', $schoolId)->pluck('id'))
                ->count(),
            'substitution_assignments' => DB::table('substitution_assignments')
                ->whereIn('timetable_entry_id', DB::table('timetable_entries')
                    ->whereIn('school_class_id', $this->classIdsForSchool($schoolId))->pluck('id'))
                ->count(),
            // 'exams' is left out — exams aren't single-school in this app.
            default => 0,
        };
    }

    private function classIdsForSchool(int $schoolId): \Illuminate\Support\Collection
    {
        return DB::table('school_classes')->where('school_id', $schoolId)->pluck('id');
    }

    /**
     * Delete every row dependent on one exam (and optionally one school).
     */
    private function wipeForExam(int $examId, ?int $schoolId = null): array
    {
        $deleted = [];
        $classIds = $schoolId ? $this->classIdsForSchool($schoolId) : null;

        try {
            Schema::disableForeignKeyConstraints();
            DB::transaction(function () use ($examId, $schoolId, $classIds, &$deleted) {
                $scopeSchool = fn ($q) => $schoolId ? $q->where('school_id', $schoolId) : $q;
                $scopeClass = fn ($q) => $classIds ? $q->whereIn('school_class_id', $classIds) : $q;

                if (Schema::hasTable('result_amendments') && Schema::hasTable('results')) {
                    $resultIds = $scopeSchool(DB::table('results')->where('exam_id', $examId))->pluck('id');
                    $deleted['result_amendments'] = $resultIds->isEmpty() ? 0
                        : DB::table('result_amendments')->whereIn('result_id', $resultIds)->delete();
                }

                $deleted['result_submissions'] = $scopeSchool(
                    DB::table('result_submissions')->where('exam_id', $examId)
                )->delete();

                $deleted['results'] = $scopeSchool(
                    DB::table('results')->where('exam_id', $examId)
                )->delete();

                $deleted['marks_submissions'] = $scopeSchool(
                    DB::table('marks_submissions')->where('exam_id', $examId)
                )->delete();

                $deleted['marks'] = $scopeSchool(
                    DB::table('marks')->where('exam_id', $examId)
                )->delete();

                // Invigilators / seats: scoped indirectly when school filter is on.
                if ($schoolId) {
                    $scheduleIds = DB::table('exam_schedules')
                        ->where('exam_id', $examId)
                        ->whereIn('school_class_id', $classIds)->pluck('id');
                    $deleted['exam_invigilators'] = $scheduleIds->isEmpty() ? 0
                        : DB::table('exam_invigilators')->whereIn('exam_schedule_id', $scheduleIds)->delete();

                    $studentIds = DB::table('students')->where('school_id', $schoolId)->pluck('id');
                    $deleted['exam_seats'] = $studentIds->isEmpty() ? 0
                        : DB::table('exam_seats')->where('exam_id', $examId)->whereIn('student_id', $studentIds)->delete();
                } else {
                    $deleted['exam_invigilators'] = DB::table('exam_invigilators')->where('exam_id', $examId)->delete();
                    $deleted['exam_seats'] = DB::table('exam_seats')->where('exam_id', $examId)->delete();
                }

                $deleted['exam_schedules'] = $scopeClass(
                    DB::table('exam_schedules')->where('exam_id', $examId)
                )->delete();

                $deleted['exam_subjects'] = $scopeClass(
                    DB::table('exam_subjects')->where('exam_id', $examId)
                )->delete();

                $deleted['exam_schools'] = $schoolId
                    ? DB::table('exam_schools')->where('exam_id', $examId)->where('school_id', $schoolId)->delete()
                    : DB::table('exam_schools')->where('exam_id', $examId)->delete();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $deleted;
    }

    /**
     * Wipe every school-scoped exam dependent row across all exams.
     * Leaves the parent `exams` rows alone (may still be attached to other schools).
     */
    private function wipeExamScopedToSchool(int $schoolId): array
    {
        $deleted = [];
        $classIds = $this->classIdsForSchool($schoolId);

        try {
            Schema::disableForeignKeyConstraints();
            DB::transaction(function () use ($schoolId, $classIds, &$deleted) {
                $resultIds = DB::table('results')->where('school_id', $schoolId)->pluck('id');
                $deleted['result_amendments'] = $resultIds->isEmpty() ? 0
                    : DB::table('result_amendments')->whereIn('result_id', $resultIds)->delete();

                $deleted['result_submissions'] = DB::table('result_submissions')->where('school_id', $schoolId)->delete();
                $deleted['results'] = DB::table('results')->where('school_id', $schoolId)->delete();
                $deleted['marks_submissions'] = DB::table('marks_submissions')->where('school_id', $schoolId)->delete();
                $deleted['marks'] = DB::table('marks')->where('school_id', $schoolId)->delete();

                $scheduleIds = $classIds->isEmpty() ? collect()
                    : DB::table('exam_schedules')->whereIn('school_class_id', $classIds)->pluck('id');
                $deleted['exam_invigilators'] = $scheduleIds->isEmpty() ? 0
                    : DB::table('exam_invigilators')->whereIn('exam_schedule_id', $scheduleIds)->delete();

                $studentIds = DB::table('students')->where('school_id', $schoolId)->pluck('id');
                $deleted['exam_seats'] = $studentIds->isEmpty() ? 0
                    : DB::table('exam_seats')->whereIn('student_id', $studentIds)->delete();

                $deleted['exam_schedules'] = $classIds->isEmpty() ? 0
                    : DB::table('exam_schedules')->whereIn('school_class_id', $classIds)->delete();
                $deleted['exam_subjects'] = $classIds->isEmpty() ? 0
                    : DB::table('exam_subjects')->whereIn('school_class_id', $classIds)->delete();
                $deleted['exam_schools'] = DB::table('exam_schools')->where('school_id', $schoolId)->delete();
                // exams rows: keep
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $deleted;
    }

    /**
     * Empty a list of tables in the given (FK-safe) order. Uses DELETE rather
     * than TRUNCATE — MySQL refuses to TRUNCATE a table referenced by any FK
     * even with FOREIGN_KEY_CHECKS=0, which silently broke wipes earlier.
     * Returns [table => rows-removed].
     */
    private function truncateTables(array $tables): array
    {
        $deleted = [];

        try {
            Schema::disableForeignKeyConstraints();
            foreach ($tables as $t) {
                if (!Schema::hasTable($t)) {
                    continue;
                }
                $deleted[$t] = DB::table($t)->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $deleted;
    }

    private function fmtDeleted(array $deleted): string
    {
        $parts = [];
        foreach ($deleted as $t => $n) {
            if ($n > 0) {
                $parts[] = "{$t}: {$n}";
            }
        }
        return $parts ? implode(', ', $parts) : 'no rows';
    }
}
