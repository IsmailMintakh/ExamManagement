<?php
namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search across the system. Returns grouped results.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = trim((string) $request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $q = "%{$query}%";

        // Students
        $studentsQuery = Student::query()
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', $q)
                  ->orWhere('admission_no', 'like', $q)
                  ->orWhere('roll_no', 'like', $q);
            })
            ->when(!$user->isSuperAdmin(), fn ($qb) => $qb->where('school_id', $user->school_id));
        if ($user->hasRole('class-teacher')) {
            $sectionIds = Section::where('class_teacher_id', $user->id)->pluck('id');
            $studentsQuery->whereIn('section_id', $sectionIds);
        }
        $students = $studentsQuery
            ->with(['schoolClass:id,name', 'section:id,name'])
            ->limit(8)
            ->get()
            ->map(fn ($s) => [
                'type' => 'student',
                'title' => $s->name,
                'subtitle' => "Adm #{$s->admission_no} · " . ($s->schoolClass?->name ?? '-') . ' ' . ($s->section?->name ?? ''),
                'url' => $this->studentUrl($s),
                'icon' => 'user',
            ]);

        // Exams
        $exams = collect();
        if ($user->isSuperAdmin() || $user->isSchoolAdmin() || $user->hasPermissionTo('exams.view')) {
            $exams = Exam::where('name', 'like', $q)
                ->when(!$user->isSuperAdmin(), fn ($qb) => $qb->whereHas('schools', fn ($q2) => $q2->where('schools.id', $user->school_id)))
                ->with('examType:id,name')
                ->limit(6)
                ->get()
                ->map(fn ($e) => [
                    'type' => 'exam',
                    'title' => $e->name,
                    'subtitle' => ($e->examType?->name ?? 'Exam') . ' · ' . ucfirst(str_replace('_', ' ', $e->status)),
                    'url' => route('exams.show', $e->id),
                    'icon' => 'clipboard',
                ]);
        }

        // Classes
        $classes = collect();
        if ($user->hasPermissionTo('classes.view') || $user->isSuperAdmin()) {
            $classes = SchoolClass::where('name', 'like', $q)
                ->when(!$user->isSuperAdmin(), fn ($qb) => $qb->where('school_id', $user->school_id))
                ->with('school:id,name')
                ->limit(5)
                ->get()
                ->map(fn ($c) => [
                    'type' => 'class',
                    'title' => $c->name,
                    'subtitle' => $c->school?->name,
                    'url' => route('classes.show', $c->id),
                    'icon' => 'academic',
                ]);
        }

        // Schools (super-admin only)
        $schools = collect();
        if ($user->isSuperAdmin()) {
            $schools = School::where('name', 'like', $q)
                ->orWhere('code', 'like', $q)
                ->limit(4)
                ->get()
                ->map(fn ($s) => [
                    'type' => 'school',
                    'title' => $s->name,
                    'subtitle' => $s->code,
                    'url' => route('schools.show', $s->id),
                    'icon' => 'building',
                ]);
        }

        // Users
        $users = collect();
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            $users = User::where(function ($w) use ($q) {
                $w->where('name', 'like', $q)->orWhere('email', 'like', $q);
            })
                ->when(!$user->isSuperAdmin(), fn ($qb) => $qb->where('school_id', $user->school_id))
                ->with('roles:id,name')
                ->limit(5)
                ->get()
                ->map(fn ($u) => [
                    'type' => 'user',
                    'title' => $u->name,
                    'subtitle' => $u->email . ' · ' . $u->roles->pluck('name')->implode(', '),
                    'url' => route('users.edit', $u->id),
                    'icon' => 'users',
                ]);
        }

        return response()->json([
            'results' => [
                ['label' => 'Students', 'items' => $students],
                ['label' => 'Exams', 'items' => $exams],
                ['label' => 'Classes', 'items' => $classes],
                ['label' => 'Schools', 'items' => $schools],
                ['label' => 'Users', 'items' => $users],
            ],
        ]);
    }

    protected function studentUrl($student): string
    {
        return route('students.show', $student->id);
    }

    /**
     * Quick-jump list of common actions for the palette.
     */
    public function quickActions(Request $request): JsonResponse
    {
        $user = $request->user();
        $actions = [];
        if ($user->isSuperAdmin()) {
            $actions[] = ['title' => 'Create Exam', 'subtitle' => 'New exam setup', 'url' => route('exams.create'), 'icon' => 'plus'];
            $actions[] = ['title' => 'Add School', 'subtitle' => 'Register new school', 'url' => route('schools.create'), 'icon' => 'plus'];
        }
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            $actions[] = ['title' => 'Add Student', 'subtitle' => 'Enroll student', 'url' => route('students.create'), 'icon' => 'plus'];
            $actions[] = ['title' => 'Add User', 'subtitle' => 'Create teacher/staff', 'url' => route('users.create'), 'icon' => 'plus'];
        }
        $actions[] = ['title' => 'Marks Entry', 'subtitle' => 'Enter student marks', 'url' => route('marks.index'), 'icon' => 'document'];
        $actions[] = ['title' => 'Results', 'subtitle' => 'View results', 'url' => route('results.index'), 'icon' => 'chart'];
        $actions[] = ['title' => 'Reports', 'subtitle' => 'Generate reports', 'url' => route('reports.index'), 'icon' => 'document'];
        $actions[] = ['title' => 'Help & Docs', 'subtitle' => 'Get help', 'url' => route('help'), 'icon' => 'help'];

        return response()->json(['actions' => $actions]);
    }
}
