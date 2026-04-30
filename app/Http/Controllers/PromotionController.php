<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->can('promotion.view'), 403);

        $currentSession = AcademicSession::currentSession();

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with('school')
            ->ordered()
            ->get();

        $classIds = $classes->pluck('id')->all();

        $totals = Student::query()
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->whereIn('school_class_id', $classIds)
            ->selectRaw('school_class_id, COUNT(*) as total')
            ->groupBy('school_class_id')
            ->pluck('total', 'school_class_id');

        $promoted = Student::query()
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->whereIn('school_class_id', $classIds)
            ->whereNotNull('promotion_status')
            ->selectRaw('school_class_id, COUNT(*) as total')
            ->groupBy('school_class_id')
            ->pluck('total', 'school_class_id');

        $classData = $classes->map(function ($class) use ($totals, $promoted) {
            $total = (int) ($totals[$class->id] ?? 0);
            $done = (int) ($promoted[$class->id] ?? 0);

            return [
                'id' => $class->id,
                'name' => $class->name,
                'school_id' => $class->school_id,
                'school_name' => $class->school?->name,
                'total_students' => $total,
                'promoted_count' => $done,
                'pending_count' => max(0, $total - $done),
            ];
        });

        return Inertia::render('Promotion/Index', [
            'classes' => $classData,
            'currentSession' => $currentSession,
        ]);
    }

    public function show(SchoolClass $class): Response
    {
        $user = request()->user();

        abort_unless($user->can('promotion.view'), 403);

        if (!$user->isSuperAdmin() && $class->school_id !== $user->school_id) {
            abort(403);
        }

        $currentSession = AcademicSession::currentSession();

        $students = Student::query()
            ->where('school_class_id', $class->id)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->where('status', 'active')
            ->with(['section'])
            ->orderBy('roll_no')
            ->orderBy('name')
            ->get();

        // Determine "next" class within the same school based on sort_order, then numeric_name, then id
        $nextClasses = SchoolClass::query()
            ->where('school_id', $class->school_id)
            ->where('id', '!=', $class->id)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'school_id', 'sort_order', 'numeric_name']);

        $sections = Section::query()
            ->whereIn('school_class_id', $nextClasses->pluck('id')->push($class->id))
            ->active()
            ->get(['id', 'name', 'school_class_id']);

        $class->load('school');

        return Inertia::render('Promotion/Show', [
            'class' => $class,
            'students' => $students,
            'nextClasses' => $nextClasses,
            'sections' => $sections,
            'currentSession' => $currentSession,
        ]);
    }

    public function process(Request $request, SchoolClass $class): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('promotion.process'), 403);

        if (!$user->isSuperAdmin() && $class->school_id !== $user->school_id) {
            abort(403);
        }

        $validated = $request->validate([
            'students' => ['required', 'array', 'min:1'],
            'students.*.id' => ['required', 'integer', 'exists:students,id'],
            'students.*.action' => ['required', 'string', 'in:promote,retain,graduate'],
            'students.*.target_class_id' => ['nullable', 'integer', 'exists:school_classes,id', 'required_if:students.*.action,promote'],
            'students.*.target_section_id' => ['nullable', 'integer', 'exists:sections,id', 'required_if:students.*.action,promote'],
        ]);

        $counts = ['promoted' => 0, 'retained' => 0, 'graduated' => 0];

        DB::transaction(function () use ($validated, $class, &$counts) {
            $now = now();

            foreach ($validated['students'] as $entry) {
                $student = Student::where('id', $entry['id'])
                    ->where('school_class_id', $class->id)
                    ->first();

                if (!$student) {
                    continue;
                }

                $action = $entry['action'];

                if ($action === 'promote') {
                    $student->previous_class_id = $student->school_class_id;
                    $student->school_class_id = $entry['target_class_id'];
                    $student->section_id = $entry['target_section_id'];
                    $student->promotion_status = 'promoted';
                    $student->promoted_at = $now;
                    $student->save();
                    $counts['promoted']++;
                } elseif ($action === 'retain') {
                    $student->promotion_status = 'retained';
                    $student->promoted_at = $now;
                    $student->save();
                    $counts['retained']++;
                } elseif ($action === 'graduate') {
                    $student->status = 'graduated';
                    $student->promotion_status = 'graduated';
                    $student->promoted_at = $now;
                    $student->save();
                    $counts['graduated']++;
                }
            }
        });

        $message = sprintf(
            'Promotion processed: %d promoted, %d retained, %d graduated.',
            $counts['promoted'],
            $counts['retained'],
            $counts['graduated']
        );

        return redirect()->route('promotion.index')->with('success', $message);
    }
}
