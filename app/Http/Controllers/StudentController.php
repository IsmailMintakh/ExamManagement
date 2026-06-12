<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Models\AcademicSession;
use App\Models\IssuedCertificate;
use App\Models\Mark;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Student::class);

        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        // Use filled() not has() — has() returns true even when ?class_id= is in
        // the URL with no value, which would then filter WHERE class_id = ''
        // and silently drop every row. filled() requires a non-empty value.
        $students = Student::query()
            ->when($user->isSuperAdmin() && $request->filled('school_id'),
                fn ($q) => $q->where('school_id', $request->input('school_id')))
            ->when($user->isSchoolAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->when($user->isClassTeacher(), function ($q) use ($user) {
                $sectionIds = Section::where('class_teacher_id', $user->id)->pluck('id');
                $q->whereIn('section_id', $sectionIds);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                       ->orWhere('admission_no', 'like', "%{$search}%")
                       ->orWhere('roll_no', 'like', "%{$search}%")
                       ->orWhere('father_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('class_id'), fn ($q) => $q->where('school_class_id', $request->input('class_id')))
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->input('section_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->with(['school', 'schoolClass', 'section'])
            // No explicit orderBy here — Student model's `byRollNo` global
            // scope already sorts by class → section → numeric roll_no.
            ->paginate(15)
            ->withQueryString();

        $schools = $user->isSuperAdmin() ? School::active()->orderBy('name')->get(['id', 'name']) : [];

        // ── Class teachers + subject teachers only see THEIR classes ──
        // Compute the class/section ID set the teacher is allowed to filter by:
        //   - admin (super / school) → every class in their school
        //   - class teacher          → the classes of their assigned sections
        //   - subject teacher        → the classes they teach via SubjectTeacher
        $isAdmin = $user->isSuperAdmin() || $user->isSchoolAdmin();
        $allowedClassIds = null;
        $allowedSectionIds = null;
        if (!$isAdmin) {
            $ctSectionIds = Section::where('class_teacher_id', $user->id)->pluck('id');
            $stRows = \App\Models\SubjectTeacher::where('user_id', $user->id)
                ->where('is_active', true)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->get(['school_class_id', 'section_id']);
            $allowedSectionIds = $ctSectionIds->merge($stRows->pluck('section_id'))->unique()->values();
            $allowedClassIds = $stRows->pluck('school_class_id')
                ->merge(Section::whereIn('id', $ctSectionIds)->pluck('school_class_id'))
                ->unique()->values();
        }

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->when($allowedClassIds, fn ($q) => $q->whereIn('id', $allowedClassIds))
            ->ordered()->get(['id', 'name', 'school_id']);

        // Sections carry school_class_id so the frontend can cascade-filter
        // when a class is picked. Also carry the class name so the dropdown
        // can render "Class 8 — A" instead of just "A" (which previously
        // showed as "A, A, A" — one per class — and looked broken).
        $sections = Section::query()
            ->whereHas('schoolClass', function ($q) use ($user) {
                $q->when(!$user->isSuperAdmin(), fn ($q2) => $q2->where('school_id', $user->school_id));
            })
            ->when($allowedSectionIds, fn ($q) => $q->whereIn('id', $allowedSectionIds))
            ->with('schoolClass:id,name,sort_order')
            ->get(['id', 'name', 'school_class_id'])
            ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->sort_order ?? 9999, $s->name))
            ->values()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => trim(($s->schoolClass?->name ?? '') . ' — ' . $s->name),
                'school_class_id' => $s->school_class_id,
            ]);

        // KPI strip — counts respect the same scope as the list above
        // (super-admin sees everything, school-admin sees their school,
        // class teacher sees their sections) but ignore the search/class/
        // status filters so the strip stays meaningful as the user filters.
        $statusCounts = Student::query()
            ->when($user->isSuperAdmin() && $request->filled('school_id'),
                fn ($q) => $q->where('school_id', $request->input('school_id')))
            ->when($user->isSchoolAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->when($user->isClassTeacher(), function ($q) use ($user) {
                $sectionIds = Section::where('class_teacher_id', $user->id)->pluck('id');
                $q->whereIn('section_id', $sectionIds);
            })
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return Inertia::render('Students/Index', [
            'students' => $students,
            'schools' => $schools,
            'classes' => $classes,
            'sections' => $sections,
            'isSuperAdmin' => $user->isSuperAdmin(),
            'filters' => $request->only(['search', 'school_id', 'class_id', 'section_id', 'status']),
            'statusCounts' => [
                'total' => (int) $statusCounts->sum(),
                'active' => (int) ($statusCounts['active'] ?? 0),
                'inactive' => (int) ($statusCounts['inactive'] ?? 0),
                'graduated' => (int) ($statusCounts['graduated'] ?? 0),
                'transferred' => (int) ($statusCounts['transferred'] ?? 0),
            ],
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', Student::class);

        $user = $request->user();
        $currentSession = AcademicSession::currentSession();

        if (!$currentSession) {
            return redirect()->route('academic-sessions.create')
                ->with('warning', 'Please create an academic session before adding students.');
        }

        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $user->school_id)->get(['id', 'name']);

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name', 'school_id']);

        if ($classes->isEmpty()) {
            return redirect()->route('classes.create')
                ->with('warning', 'Please add at least one class before adding students.');
        }

        $sections = Section::query()
            ->whereHas('schoolClass', function ($q) use ($user) {
                $q->when(!$user->isSuperAdmin(), fn ($q2) => $q2->where('school_id', $user->school_id));
            })
            ->active()->get(['id', 'name', 'school_class_id']);

        if ($sections->isEmpty()) {
            return redirect()->route('sections.create')
                ->with('warning', 'Please add at least one section before adding students.');
        }

        // ─── Pre-fill school / class / section from WHO is adding ───
        // A student always belongs to the teacher's school. Class teachers
        // default to their section; subject teachers to a section they teach.
        // All still editable on the form (super-admins choose freely).
        $defaultSchoolId = $user->isSuperAdmin() ? $schools->first()?->id : $user->school_id;
        $defaultSectionId = null;
        $defaultClassId = null;

        // Role-agnostic: use the actual assignment, not the role name.
        // 1) Section this user is the class teacher of.
        $sec = Section::where('class_teacher_id', $user->id)
            ->where('is_active', true)
            ->first();
        if ($sec) {
            $defaultSectionId = $sec->id;
            $defaultClassId = $sec->school_class_id;
        }
        // 2) Otherwise a section/class they're assigned to teach.
        if (!$defaultSectionId) {
            $st = \App\Models\SubjectTeacher::where('user_id', $user->id)
                ->where('is_active', true)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->first()
                // fall back ignoring session if assignments exist for another
                ?? \App\Models\SubjectTeacher::where('user_id', $user->id)
                    ->where('is_active', true)->first();
            if ($st) {
                $defaultSectionId = $st->section_id;
                $defaultClassId = $st->school_class_id;
            }
        }

        return Inertia::render('Students/Create', [
            'schools' => $schools,
            'classes' => $classes,
            'sections' => $sections,
            'isSuperAdmin' => $user->isSuperAdmin(),
            'defaults' => [
                'school_id' => $defaultSchoolId,
                'school_class_id' => $defaultClassId,
                'section_id' => $defaultSectionId,
            ],
            'currentSession' => $currentSession,
            // Initial smart defaults so a fresh form is half-filled. The frontend
            // re-fetches via the /students/smart-defaults endpoint when the user
            // changes school/section so suggestions stay accurate.
            'smartDefaults' => $this->computeSmartDefaults(
                schoolId: $user->isSuperAdmin() ? $schools->first()?->id : $user->school_id,
                sectionId: null,
                currentSession: $currentSession,
            ),
        ]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['academic_session_id'])) {
            $data['academic_session_id'] = AcademicSession::currentSession()?->id;
        }

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students/photos', 'public');
        }

        Student::create($data);

        return redirect()->route('students.index')->with('success', 'Student created successfully.');
    }

    public function show(Student $student): Response
    {
        $this->authorize('view', $student);

        $student->load(['school', 'schoolClass', 'section', 'academicSession']);

        // ---- Marks (per-subject, per-exam) ----
        $marks = Mark::where('student_id', $student->id)
            ->with(['subject:id,name,code', 'exam:id,name,start_date'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'exam_id' => $m->exam_id,
                'exam_name' => $m->exam?->name,
                'subject_name' => $m->subject?->name,
                'subject_code' => $m->subject?->code,
                'marks_obtained' => (float) $m->marks_obtained,
                'total_marks' => (float) $m->total_marks,
                'grace_marks' => (float) ($m->grace_marks ?? 0),
                'percentage' => $m->total_marks > 0
                    ? round((((float) $m->marks_obtained + (float) ($m->grace_marks ?? 0)) / $m->total_marks) * 100, 1)
                    : 0,
                'is_absent' => (bool) $m->is_absent,
                'status' => $m->status,
            ]);

        // ---- Results (per-exam summary) ----
        $results = Result::where('student_id', $student->id)
            ->with(['exam:id,name,start_date,exam_type_id', 'exam.examType:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'exam_id' => $r->exam_id,
                'exam_name' => $r->exam?->name,
                'exam_type' => $r->exam?->examType?->name,
                'exam_date' => $r->exam?->start_date,
                'total_marks' => (float) $r->total_marks,
                'obtained_marks' => (float) $r->obtained_marks,
                'percentage' => round((float) $r->percentage, 1),
                'grade' => $r->grade,
                'position' => $r->position,
                'total_students' => $r->total_students,
                'is_passed' => (bool) $r->is_passed,
                'subjects_passed' => $r->subjects_passed,
                'subjects_failed' => $r->subjects_failed,
            ]);

        // ---- Mark trend (chronological %) ----
        $trend = $results->sortBy('exam_date')->values()->map(fn ($r) => [
            'label' => $r['exam_name'],
            'percentage' => $r['percentage'],
        ]);

        // ---- Transfers ----
        $transfers = StudentTransfer::where('student_id', $student->id)
            ->with(['fromSchool:id,name', 'toSchool:id,name', 'fromClass:id,name', 'toClass:id,name'])
            ->orderByDesc('initiated_at')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'from' => $t->fromSchool?->name . ' · ' . $t->fromClass?->name,
                'to' => $t->toSchool?->name . ' · ' . $t->toClass?->name,
                'reason' => $t->reason,
                'status' => $t->status,
                'date' => $t->initiated_at,
            ]);

        // ---- Certificates earned ----
        $certificates = IssuedCertificate::where('student_id', $student->id)
            ->with(['template:id,name,type', 'exam:id,name'])
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'number' => $c->certificate_number,
                'type' => $c->type,
                'template_name' => $c->template?->name,
                'exam_name' => $c->exam?->name,
                'issued_at' => $c->issued_at,
            ]);

        // ---- Stats ----
        $passedResults = $results->filter(fn ($r) => $r['is_passed']);
        $stats = [
            'examsTaken' => $results->count(),
            'examsPassed' => $passedResults->count(),
            'avgScore' => $results->count() ? round($results->avg('percentage'), 1) : 0,
            'bestPercentage' => $results->max('percentage') ?: 0,
            'bestRank' => $results->whereNotNull('position')->min('position'),
            'certificatesEarned' => $certificates->count(),
            'currentStreak' => $this->computePassStreak($results),
        ];

        // ---- Build chronological timeline (single feed of all events) ----
        $timeline = collect();

        foreach ($results as $r) {
            $timeline->push([
                'type' => 'exam_result',
                'date' => $r['exam_date'] ?? now()->toDateString(),
                'title' => $r['exam_name'],
                'meta' => $r,
            ]);
        }
        foreach ($transfers as $t) {
            $timeline->push([
                'type' => 'transfer',
                'date' => $t['date'] ?? now()->toDateString(),
                'title' => "Transferred from {$t['from']} to {$t['to']}",
                'meta' => $t,
            ]);
        }
        foreach ($certificates as $c) {
            $timeline->push([
                'type' => 'certificate',
                'date' => $c['issued_at'] ?? now()->toDateString(),
                'title' => $c['template_name'] . ' awarded',
                'meta' => $c,
            ]);
        }
        // Admission event (always at the bottom of timeline)
        $timeline->push([
            'type' => 'admission',
            'date' => $student->created_at?->toDateString() ?? now()->toDateString(),
            'title' => "Admitted to {$student->school?->name}",
            'meta' => [
                'class' => $student->schoolClass?->name,
                'section' => $student->section?->name,
            ],
        ]);

        $timeline = $timeline->sortByDesc('date')->values();

        return Inertia::render('Students/Show', [
            'student' => $student,
            'marks' => $marks,
            'results' => $results,
            'trend' => $trend,
            'transfers' => $transfers,
            'certificates' => $certificates,
            'timeline' => $timeline,
            'stats' => $stats,
        ]);
    }

    /**
     * Count consecutive most-recent passed results.
     */
    protected function computePassStreak($results): int
    {
        $sorted = $results->sortByDesc('exam_date')->values();
        $streak = 0;
        foreach ($sorted as $r) {
            if ($r['is_passed']) $streak++;
            else break;
        }
        return $streak;
    }

    public function edit(Student $student): Response
    {
        $this->authorize('update', $student);

        $user = request()->user();

        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $user->school_id)->get(['id', 'name']);

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name', 'school_id']);

        $sections = Section::query()
            ->whereHas('schoolClass', function ($q) use ($user) {
                $q->when(!$user->isSuperAdmin(), fn ($q2) => $q2->where('school_id', $user->school_id));
            })
            ->active()->get(['id', 'name', 'school_class_id']);

        return Inertia::render('Students/Create', [
            'student' => $student,
            'schools' => $schools,
            'classes' => $classes,
            'sections' => $sections,
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);

        // Auto-fill academic_session_id BEFORE validation so older clients,
        // mobile PWA forms, or any form that omits the field still pass.
        // Falls back to the student's existing session, then the current one.
        if (!$request->filled('academic_session_id')) {
            $request->merge([
                'academic_session_id' => $student->academic_session_id
                    ?? AcademicSession::currentSession()?->id,
            ]);
        }

        $validated = $request->validate([
            'admission_no' => ['required', 'string', 'max:50', 'unique:students,admission_no,' . $student->id],
            'roll_no' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            // Up to 4 MB so modern phone camera photos pass without
            // pre-compression. Keep mime list permissive for phone uploads.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'address' => ['nullable', 'string', 'max:500'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'religion' => ['nullable', 'string', 'max:50'],
            // CNIC / B-Form: 13 digits, formatted as 12345-1234567-1
            'cnic' => ['nullable', 'string', 'max:18', 'regex:/^[0-9]{5}-?[0-9]{7}-?[0-9]{1}$/'],
            'school_id' => ['required', 'exists:schools,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'academic_session_id' => ['nullable', 'exists:academic_sessions,id'],
            'status' => ['nullable', 'string', 'in:active,inactive,transferred,graduated'],
        ]);

        if ($request->hasFile('photo')) {
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
            $validated['photo'] = $request->file('photo')->store('students/photos', 'public');
        }

        $student->update($validated);

        return redirect()->route('students.index')->with('success', 'Student updated successfully.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->authorize('delete', $student);

        $student->delete();

        return redirect()->route('students.index')->with('success', 'Student deleted successfully.');
    }

    /**
     * Bulk delete — accepts an array of student IDs. Each is policy-authorized
     * individually, so a principal can only delete their own school's students.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:students,id'],
        ]);

        $user = $request->user();
        $deleted = 0;
        $skipped = 0;

        foreach (Student::whereIn('id', $data['ids'])->get() as $student) {
            if ($user->can('delete', $student)) {
                $student->delete();
                $deleted++;
            } else {
                $skipped++;
            }
        }

        $msg = "{$deleted} student" . ($deleted === 1 ? '' : 's') . ' deleted.';
        if ($skipped > 0) $msg .= " {$skipped} skipped (no permission).";

        return redirect()->route('students.index')->with($deleted > 0 ? 'success' : 'warning', $msg);
    }

    /**
     * Bulk export — streams selected students as CSV.
     */
    public function bulkExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:students,id'],
        ]);

        $user = $request->user();
        $students = Student::whereIn('id', $data['ids'])
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with(['school:id,name', 'schoolClass:id,name', 'section:id,name'])
            ->orderBy('school_id')->orderBy('school_class_id')->orderBy('section_id')->orderBy('roll_no')
            ->get();

        $filename = 'students-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($students) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Admission No', 'Roll No', 'Name', 'Father Name', 'Mother Name',
                'Gender', 'Date of Birth', 'Guardian Phone', 'Address', 'Blood Group',
                'School', 'Class', 'Section', 'Status',
            ]);
            foreach ($students as $s) {
                fputcsv($out, [
                    $s->admission_no, $s->roll_no, $s->name, $s->father_name, $s->mother_name,
                    $s->gender, $s->date_of_birth, $s->guardian_phone, $s->address, $s->blood_group,
                    $s->school?->name, $s->schoolClass?->name, $s->section?->name, $s->status,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Generate a single student ID card (front + back on one A4).
     */
    public function idCard(Student $student)
    {
        $this->authorize('view', $student);

        $student->load(['school', 'schoolClass', 'section', 'academicSession']);

        $pdf = Pdf::loadView('reports.student-id-card', ['student' => $student])
            ->setPaper('a4', 'portrait');

        return $pdf->stream("id-card-{$student->admission_no}.pdf");
    }

    /**
     * Generate ID cards for many students (4 fronts per page, then backs on next).
     */
    public function bulkIdCards(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:students,id'],
        ]);

        $user = $request->user();
        $students = Student::whereIn('id', $data['ids'])
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with(['school', 'schoolClass', 'section', 'academicSession'])
            ->orderBy('school_class_id')->orderBy('section_id')->orderBy('roll_no')
            ->get();

        if ($students->isEmpty()) {
            return redirect()->back()->with('error', 'No students found for ID card generation.');
        }

        $pdf = Pdf::loadView('reports.student-id-cards-bulk', ['students' => $students])
            ->setPaper('a4', 'portrait');

        return $pdf->stream('student-id-cards-' . now()->format('Y-m-d') . '.pdf');
    }

    public function import(Request $request): Response
    {
        $this->authorize('create', Student::class);

        $user = $request->user();

        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $user->school_id)->get(['id', 'name']);

        $classes = SchoolClass::query()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->active()->ordered()->get(['id', 'name', 'school_id']);

        $sections = Section::query()
            ->whereHas('schoolClass', function ($q) use ($user) {
                $q->when(!$user->isSuperAdmin(), fn ($q2) => $q2->where('school_id', $user->school_id));
            })
            ->active()->get(['id', 'name', 'school_class_id']);

        return Inertia::render('Students/Import', [
            'schools' => $schools,
            'classes' => $classes,
            'sections' => $sections,
        ]);
    }

    public function processImport(Request $request): RedirectResponse
    {
        $this->authorize('create', Student::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'school_id' => ['required', 'exists:schools,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
        ]);

        try {
            $import = new \App\Imports\StudentsImport(
                $request->input('school_id'),
                $request->input('school_class_id'),
                $request->input('section_id'),
                $request->input('academic_session_id')
            );

            Excel::import($import, $request->file('file'));

            $count = $import->getRowCount();

            return redirect()->route('students.index')
                ->with('success', "{$count} students imported successfully.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Compute smart defaults for the student-create form.
     * Returns next admission_no for the school + next roll_no for the section
     * + the current academic session id. Both numeric values are derived from
     * existing data so a teacher just hits Enter through the form.
     */
    public function smartDefaults(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Student::class);

        $defaults = $this->computeSmartDefaults(
            schoolId: $request->integer('school_id') ?: $request->user()->school_id,
            sectionId: $request->integer('section_id') ?: null,
            currentSession: AcademicSession::currentSession(),
        );

        return response()->json($defaults);
    }

    /**
     * Internal helper used by both create() and smartDefaults().
     *
     * Algorithm:
     *  - admission_no: takes the highest numeric admission_no in the school,
     *    increments by 1. If the school has no students yet, starts at 1.
     *    If existing values are non-numeric (e.g. "GBHSS-2024-001") we just
     *    return null and let the user type it themselves.
     *  - roll_no: same idea but per section.
     *  - academic_session_id: always the current session.
     */
    protected function computeSmartDefaults(?int $schoolId, ?int $sectionId, ?AcademicSession $currentSession): array
    {
        $nextAdmissionNo = null;
        if ($schoolId) {
            $maxAdm = Student::where('school_id', $schoolId)
                ->whereRaw("admission_no REGEXP '^[0-9]+$'")
                ->selectRaw('MAX(CAST(admission_no AS UNSIGNED)) as mx')
                ->value('mx');
            // Even if no plain-numeric values exist yet, suggest "1" as a sane start
            $nextAdmissionNo = (int) ($maxAdm ?? 0) + 1;
        }

        $nextRollNo = null;
        if ($sectionId) {
            $maxRoll = Student::where('section_id', $sectionId)
                ->whereRaw("roll_no REGEXP '^[0-9]+$'")
                ->selectRaw('MAX(CAST(roll_no AS UNSIGNED)) as mx')
                ->value('mx');
            $nextRollNo = (int) ($maxRoll ?? 0) + 1;
        }

        return [
            'admission_no' => $nextAdmissionNo ? (string) $nextAdmissionNo : null,
            'roll_no' => $nextRollNo ? (string) $nextRollNo : null,
            'academic_session_id' => $currentSession?->id,
        ];
    }
}
