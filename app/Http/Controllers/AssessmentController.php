<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AssessmentMark;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Primary-section Assessment marks entry.
 *
 * Only class teachers of primary-stage (ECD–5) sections can enter assessment
 * marks — the 10-mark overall conduct/participation score that feeds the
 * Annual Result calculation. Subject teachers and admins of non-primary
 * sections never see this surface.
 */
class AssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        // Only class teachers of active primary sections may use this page.
        // Admins (super/school) can land here too to review/override, but
        // the section list is the same — sections they lead OR (for admins)
        // every primary section in their scope.
        $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();

        $sectionsQuery = Section::query()
            ->with(['schoolClass:id,name,stage,school_id'])
            ->where('is_active', true)
            ->whereHas('schoolClass', fn ($q) => $q->primary());

        if ($isAdmin) {
            if (!$user->isSuperAdmin()) {
                $sectionsQuery->whereHas('schoolClass',
                    fn ($q) => $q->where('school_id', $user->school_id));
            }
        } else {
            $sectionsQuery->where('class_teacher_id', $user->id);
        }

        $sections = $sectionsQuery->get()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'class_name' => $s->schoolClass?->name,
            'school_class_id' => $s->school_class_id,
        ]);

        if ($sections->isEmpty()) {
            return Inertia::render('Assessment/Index', [
                'sections' => [],
                'activeSection' => null,
                'students' => [],
                'existing' => (object) [],
                'currentSession' => $currentSession ? ['id' => $currentSession->id, 'name' => $currentSession->name] : null,
                'config' => $this->config(),
            ]);
        }

        $sectionId = (int) ($request->input('section') ?: $sections->first()['id']);
        $active = $sections->firstWhere('id', $sectionId) ?? $sections->first();

        $students = Student::where('section_id', $active['id'])
            ->active()
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->orderBy('roll_no')
            ->orderBy('name')
            ->get(['id', 'name', 'roll_no', 'admission_no', 'father_name']);

        $existing = AssessmentMark::where('section_id', $active['id'])
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->get(['student_id', 'marks_obtained', 'marks_total', 'passing_marks', 'remarks', 'updated_at'])
            ->keyBy('student_id');

        return Inertia::render('Assessment/Index', [
            'sections' => $sections,
            'activeSection' => $active,
            'students' => $students,
            'existing' => $existing,
            'currentSession' => $currentSession ? ['id' => $currentSession->id, 'name' => $currentSession->name] : null,
            'config' => $this->config(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $currentSession = AcademicSession::currentSession();
        $config = $this->config();

        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.student_id' => ['required', 'exists:students,id'],
            'marks.*.marks_obtained' => ['required', 'numeric', 'min:0', 'max:'.$config['total']],
            'marks.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'marks.*.marks_obtained.max' => "Assessment marks cannot exceed {$config['total']}.",
        ]);

        $section = Section::with('schoolClass')->findOrFail($data['section_id']);

        // Authorize: admin OR class teacher of THIS section, AND section is
        // primary stage. Subject teachers can never enter assessment marks.
        $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();
        $isClassTeacher = (int) $section->class_teacher_id === (int) $user->id;
        abort_unless($isAdmin || $isClassTeacher, 403,
            'Only the class teacher of this section can enter assessment marks.');
        abort_unless($section->schoolClass?->isPrimaryStage(), 422,
            'Assessment marks are only enabled for primary-section classes (ECD–5).');

        abort_if(!$currentSession, 422, 'No active academic session — set one first.');

        // Validate every submitted student actually belongs to this section
        // so a malformed payload can't sneak a student into the wrong class.
        $sectionStudentIds = Student::where('section_id', $section->id)
            ->where('academic_session_id', $currentSession->id)
            ->pluck('id')
            ->flip();

        $saved = 0;
        foreach ($data['marks'] as $row) {
            if (!$sectionStudentIds->has((int) $row['student_id'])) {
                continue;
            }
            AssessmentMark::updateOrCreate(
                [
                    'student_id' => $row['student_id'],
                    'academic_session_id' => $currentSession->id,
                ],
                [
                    'school_id' => $section->schoolClass->school_id,
                    'school_class_id' => $section->school_class_id,
                    'section_id' => $section->id,
                    'marks_obtained' => (float) $row['marks_obtained'],
                    'marks_total' => $config['total'],
                    'passing_marks' => $config['passing'],
                    'remarks' => $row['remarks'] ?? null,
                    'entered_by_user_id' => $user->id,
                ]
            );
            $saved++;
        }

        return back()->with('success',
            "Saved assessment marks for {$saved} student".($saved === 1 ? '' : 's').'.');
    }

    /** Total + passing thresholds — fixed by the primary spec. */
    protected function config(): array
    {
        return ['total' => 10, 'passing' => 4];
    }
}
