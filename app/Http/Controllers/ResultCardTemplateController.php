<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\ResultCardTemplate;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ResultCardTemplateController extends Controller
{
    /**
     * The list of supported placeholder tokens (for documentation).
     */
    protected array $availablePlaceholders = [
        ['token' => '{{school_name}}', 'description' => 'School name'],
        ['token' => '{{school_address}}', 'description' => 'School address'],
        ['token' => '{{school_logo}}', 'description' => 'School logo URL'],
        ['token' => '{{student_name}}', 'description' => 'Student full name'],
        ['token' => '{{student_admission_no}}', 'description' => 'Student admission number'],
        ['token' => '{{student_roll_no}}', 'description' => 'Student roll number'],
        ['token' => '{{student_father_name}}', 'description' => "Student's father's name"],
        ['token' => '{{student_dob}}', 'description' => 'Student date of birth'],
        ['token' => '{{class_name}}', 'description' => 'Class name'],
        ['token' => '{{section_name}}', 'description' => 'Section name'],
        ['token' => '{{academic_session}}', 'description' => 'Academic session name'],
        ['token' => '{{exam_name}}', 'description' => 'Exam name'],
        ['token' => '{{exam_type}}', 'description' => 'Exam type'],
        ['token' => '{{total_marks}}', 'description' => 'Total marks across all subjects'],
        ['token' => '{{obtained_marks}}', 'description' => 'Marks obtained'],
        ['token' => '{{percentage}}', 'description' => 'Percentage'],
        ['token' => '{{grade}}', 'description' => 'Final grade'],
        ['token' => '{{position}}', 'description' => 'Position / rank'],
        ['token' => '{{status}}', 'description' => 'Pass / Fail status'],
        ['token' => '{{subjects_table}}', 'description' => 'Special: renders the subjects table'],
        ['token' => '{{principal_signature}}', 'description' => 'Principal signature image'],
        ['token' => '{{ddo_signature}}', 'description' => 'DDO signature image'],
        ['token' => '{{date}}', 'description' => 'Current date'],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only DDO can manage result card templates.');

        $templates = ResultCardTemplate::query()
            ->with(['academicSession:id,name', 'school:id,name', 'createdBy:id,name'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Inertia::render('ResultCardTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only DDO can manage result card templates.');

        $sessions = AcademicSession::orderByDesc('is_current')->orderBy('name')->get(['id', 'name']);
        $schools = School::active()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('ResultCardTemplates/Create', [
            'sessions' => $sessions,
            'schools' => $schools,
            'placeholders' => $this->availablePlaceholders,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only DDO can manage result card templates.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_session_id' => ['nullable', 'exists:academic_sessions,id'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'html_template' => ['required', 'string'],
            'footer_text' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'header_image' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('header_image')) {
            $validated['header_image'] = $request->file('header_image')->store('templates', 'public');
        }

        if (!empty($validated['is_default'])) {
            ResultCardTemplate::query()
                ->where('is_default', true)
                ->where('academic_session_id', $validated['academic_session_id'] ?? null)
                ->where('school_id', $validated['school_id'] ?? null)
                ->update(['is_default' => false]);
        }

        $validated['placeholders'] = collect($this->availablePlaceholders)->pluck('token')->toArray();
        $validated['created_by'] = $user->id;

        ResultCardTemplate::create($validated);

        return redirect()->route('result-card-templates.index')
            ->with('success', 'Result card template created successfully.');
    }

    public function show(ResultCardTemplate $resultCardTemplate): Response
    {
        $user = request()->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only DDO can manage result card templates.');

        $resultCardTemplate->load(['academicSession:id,name', 'school:id,name', 'createdBy:id,name']);

        $renderedHtml = $this->renderWithSampleData($resultCardTemplate->html_template);

        return Inertia::render('ResultCardTemplates/Show', [
            'template' => $resultCardTemplate,
            'renderedHtml' => $renderedHtml,
        ]);
    }

    public function edit(ResultCardTemplate $resultCardTemplate): Response
    {
        $user = request()->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only DDO can manage result card templates.');

        $sessions = AcademicSession::orderByDesc('is_current')->orderBy('name')->get(['id', 'name']);
        $schools = School::active()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('ResultCardTemplates/Create', [
            'template' => $resultCardTemplate,
            'sessions' => $sessions,
            'schools' => $schools,
            'placeholders' => $this->availablePlaceholders,
        ]);
    }

    public function update(Request $request, ResultCardTemplate $resultCardTemplate): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only DDO can manage result card templates.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_session_id' => ['nullable', 'exists:academic_sessions,id'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'html_template' => ['required', 'string'],
            'footer_text' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'header_image' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('header_image')) {
            if ($resultCardTemplate->header_image) {
                Storage::disk('public')->delete($resultCardTemplate->header_image);
            }
            $validated['header_image'] = $request->file('header_image')->store('templates', 'public');
        }

        if (!empty($validated['is_default'])) {
            ResultCardTemplate::query()
                ->where('id', '!=', $resultCardTemplate->id)
                ->where('is_default', true)
                ->where('academic_session_id', $validated['academic_session_id'] ?? null)
                ->where('school_id', $validated['school_id'] ?? null)
                ->update(['is_default' => false]);
        }

        $resultCardTemplate->update($validated);

        return redirect()->route('result-card-templates.index')
            ->with('success', 'Result card template updated successfully.');
    }

    public function destroy(ResultCardTemplate $resultCardTemplate): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only DDO can manage result card templates.');

        if ($resultCardTemplate->header_image) {
            Storage::disk('public')->delete($resultCardTemplate->header_image);
        }

        $resultCardTemplate->delete();

        return redirect()->route('result-card-templates.index')
            ->with('success', 'Result card template deleted successfully.');
    }

    /**
     * Live preview AJAX endpoint.
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only DDO can manage result card templates.');

        $request->validate([
            'html_template' => ['required', 'string'],
        ]);

        $rendered = $this->renderWithSampleData($request->input('html_template'));

        return response()->json([
            'html' => $rendered,
        ]);
    }

    /**
     * Replace placeholders with sample data for previewing the template.
     */
    protected function renderWithSampleData(string $html): string
    {
        $sample = [
            'school_name' => 'Government Higher Secondary School',
            'school_address' => '123 Education Street, District, State',
            'school_logo' => '',
            'student_name' => 'Ali Hasan',
            'student_admission_no' => 'ADM-2024-001',
            'student_roll_no' => '15',
            'student_father_name' => 'Muslim Hasan',
            'student_dob' => '12-05-2010',
            'class_name' => 'Class 8',
            'section_name' => 'A',
            'academic_session' => '2025-2026',
            'exam_name' => 'Annual Examination',
            'exam_type' => 'Annual',
            'total_marks' => '600',
            'obtained_marks' => '512',
            'percentage' => '85.33',
            'grade' => 'A',
            'position' => '3',
            'status' => 'PASSED',
            'principal_signature' => '',
            'ddo_signature' => '',
            'date' => now()->format('d-m-Y'),
        ];

        $subjectsTable = '<table style="width:100%;border-collapse:collapse;margin:10px 0;font-size:11px;">'
            . '<thead><tr style="background:#1a365d;color:#fff;">'
            . '<th style="padding:6px;border:1px solid #ccc;text-align:left;">Subject</th>'
            . '<th style="padding:6px;border:1px solid #ccc;">Total</th>'
            . '<th style="padding:6px;border:1px solid #ccc;">Obtained</th>'
            . '<th style="padding:6px;border:1px solid #ccc;">Grade</th>'
            . '</tr></thead><tbody>'
            . '<tr><td style="padding:6px;border:1px solid #ccc;">English</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">100</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">85</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">A</td></tr>'
            . '<tr><td style="padding:6px;border:1px solid #ccc;">Mathematics</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">100</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">92</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">A+</td></tr>'
            . '<tr><td style="padding:6px;border:1px solid #ccc;">Science</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">100</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">78</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">B+</td></tr>'
            . '<tr><td style="padding:6px;border:1px solid #ccc;">Social Studies</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">100</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">88</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">A</td></tr>'
            . '<tr><td style="padding:6px;border:1px solid #ccc;">Urdu</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">100</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">82</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">A</td></tr>'
            . '<tr><td style="padding:6px;border:1px solid #ccc;">Computer</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">100</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">87</td><td style="padding:6px;border:1px solid #ccc;text-align:center;">A</td></tr>'
            . '</tbody></table>';

        foreach ($sample as $key => $value) {
            $html = str_replace('{{' . $key . '}}', (string) $value, $html);
        }

        $html = str_replace('{{subjects_table}}', $subjectsTable, $html);

        return $html;
    }
}
