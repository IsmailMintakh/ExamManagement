<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\Exam;
use App\Models\IssuedCertificate;
use App\Models\Result;
use App\Models\School;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('certificates.view'), 403);

        $certificates = IssuedCertificate::query()
            ->when(!$user->isSuperAdmin(), function ($q) use ($user) {
                $q->whereHas('student', fn ($q2) => $q2->where('school_id', $user->school_id));
            })
            ->with(['student:id,name,admission_no,school_id', 'exam:id,name', 'template:id,name,type', 'issuedBy:id,name'])
            ->latest('issued_at')
            ->paginate(20);

        $stats = [
            'total' => IssuedCertificate::when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas('student', fn ($q2) => $q2->where('school_id', $user->school_id)))->count(),
            'merit' => IssuedCertificate::where('type', 'merit')->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas('student', fn ($q2) => $q2->where('school_id', $user->school_id)))->count(),
            'toppers' => IssuedCertificate::where('type', 'subject_topper')->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas('student', fn ($q2) => $q2->where('school_id', $user->school_id)))->count(),
            'pass' => IssuedCertificate::where('type', 'pass')->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas('student', fn ($q2) => $q2->where('school_id', $user->school_id)))->count(),
        ];

        $templatesCount = CertificateTemplate::active()->count();

        return Inertia::render('Certificates/Index', [
            'certificates' => $certificates,
            'stats' => $stats,
            'templatesCount' => $templatesCount,
        ]);
    }

    public function templates(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('certificates.templates.view'), 403);

        // Source filter (mine | library | all). Default 'all' here because a
        // school with no custom templates would see an empty list under
        // 'mine' — the DDO-shipped defaults are what makes the page useful
        // out of the box.
        $source = $this->resolveTemplateSource($request);

        $base = CertificateTemplate::query();
        $this->scopeTemplatesForUser($base, $user, $source);

        $templates = (clone $base)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $sourceCounts = $user->isSuperAdmin()
            ? null
            : [
                'mine' => $this->scopeTemplatesForUser(CertificateTemplate::query(), $user, 'mine')->count(),
                'library' => $this->scopeTemplatesForUser(CertificateTemplate::query(), $user, 'library')->count(),
                'all' => $this->scopeTemplatesForUser(CertificateTemplate::query(), $user, 'all')->count(),
            ];

        return Inertia::render('Certificates/Templates/Index', [
            'templates' => $templates,
            'filters' => ['source' => $source],
            'sourceCounts' => $sourceCounts,
        ]);
    }

    /**
     * Apply the role-based + source filter to a CertificateTemplate query.
     * Super-admin sees everything; school-admins narrow to "mine" (own school),
     * "library" (school_id IS NULL), or "all" (union).
     */
    protected function scopeTemplatesForUser($query, $user, string $source = 'all')
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return match ($source) {
            'library' => $query->whereNull('school_id'),
            'mine' => $query->where('school_id', $user->school_id),
            default /* 'all' */ => $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)->orWhereNull('school_id');
            }),
        };
    }

    protected function resolveTemplateSource(Request $request, string $default = 'all'): string
    {
        $s = $request->input('source', $default);
        return in_array($s, ['mine', 'library', 'all'], true) ? $s : $default;
    }

    public function createTemplate(Request $request): Response
    {
        abort_unless($request->user()->can('certificates.templates.manage'), 403);
        return Inertia::render('Certificates/Templates/Editor', ['template' => null]);
    }

    public function editTemplate(Request $request, CertificateTemplate $template): Response
    {
        abort_unless($request->user()->can('certificates.templates.manage'), 403);
        return Inertia::render('Certificates/Templates/Editor', ['template' => $template]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('certificates.templates.manage'), 403);
        $data = $this->validateTemplate($request);
        $data['school_id'] = $request->user()->isSuperAdmin() ? ($data['school_id'] ?? null) : $request->user()->school_id;

        if ($data['is_default'] ?? false) {
            CertificateTemplate::where('type', $data['type'])
                ->when($data['school_id'] ?? null, fn ($q, $sid) => $q->where('school_id', $sid))
                ->update(['is_default' => false]);
        }

        CertificateTemplate::create($data);
        return redirect()->route('certificates.templates')->with('success', 'Template created.');
    }

    public function updateTemplate(Request $request, CertificateTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->can('certificates.templates.manage'), 403);
        $data = $this->validateTemplate($request);

        if (($data['is_default'] ?? false) && !$template->is_default) {
            CertificateTemplate::where('type', $data['type'])
                ->when($data['school_id'] ?? null, fn ($q, $sid) => $q->where('school_id', $sid))
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($data);
        return redirect()->route('certificates.templates')->with('success', 'Template updated.');
    }

    public function deleteTemplate(Request $request, CertificateTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->can('certificates.templates.manage'), 403);
        $template->delete();
        return redirect()->back()->with('success', 'Template deleted.');
    }

    public function previewTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title_text' => 'required|string',
            'body_text' => 'required|string',
            'primary_color' => 'required|string',
            'accent_color' => 'required|string',
            'orientation' => 'required|in:portrait,landscape',
            'border_style' => 'required|string',
            'design_layout' => 'nullable|string',
            'type' => 'nullable|string',
        ]);

        $sampleData = [
            'student_name' => 'John Smith',
            'rank' => '1',
            'percentage' => '94.50',
            'grade' => 'A+',
            'exam_name' => 'Final Examination 2025-26',
            'academic_session' => '2025-26',
            'school_name' => 'Demo School',
            'class_name' => 'Class 10',
            'section_name' => 'A',
            'subject_name' => 'Mathematics',
            'date' => now()->format('d F Y'),
            'certificate_number' => 'CERT-2026-00001',
        ];

        // Include a type field so the subtitle renders
        $data['type'] = $request->input('type', 'merit');

        $school = (object) [
            'name' => 'Demo School',
            'logo' => null,
            'principal_signature' => null,
            'school_stamp' => null,
            'principal_name' => 'Mr. Principal',
        ];

        $html = view('reports.certificate', [
            'template' => (object) $data,
            'data' => $sampleData,
            'school' => $school,
        ])->render();

        return response()->json(['html' => $html]);
    }

    public function generateForm(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('certificates.generate'), 403);
        $exams = Exam::when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas('schools', fn ($q2) => $q2->where('schools.id', $user->school_id)))
            ->with('examType:id,name')
            ->where('status', 'completed')
            ->orWhere('status', 'marks_entry')
            ->latest()
            ->get(['id', 'name', 'exam_type_id', 'status']);

        $templates = CertificateTemplate::active()
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where(fn ($q2) => $q2->where('school_id', $user->school_id)->orWhereNull('school_id')))
            ->get();

        return Inertia::render('Certificates/Generate', [
            'exams' => $exams,
            'templates' => $templates,
            'initialExamId' => $request->integer('exam_id'),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('certificates.generate'), 403);
        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'type' => 'required|in:merit,subject_topper,pass,special_achievement,participation,custom',
            'template_id' => 'nullable|exists:certificate_templates,id',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        $user = $request->user();
        $exam = Exam::with('academicSession')->findOrFail($validated['exam_id']);

        // Determine template
        $template = $validated['template_id']
            ? CertificateTemplate::findOrFail($validated['template_id'])
            : CertificateTemplate::getDefaultForType($validated['type'], $user->school_id);

        if (!$template) {
            return redirect()->back()->with('error', 'No template found for this type. Create one first.');
        }

        // Determine eligible students
        $eligibleStudents = $this->getEligibleStudents($validated['type'], $exam, $user, $validated['student_ids'] ?? []);

        if ($eligibleStudents->isEmpty()) {
            return redirect()->back()->with('error', 'No eligible students found for this certificate type.');
        }

        // Compute next sequence number for this year — robust against prior deletes/re-runs.
        $yearPrefix = 'CERT-' . now()->year . '-';
        $latest = IssuedCertificate::where('certificate_number', 'like', $yearPrefix . '%')
            ->orderByDesc('id')
            ->first();

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest->certificate_number, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        $count = 0;
        try {
            DB::transaction(function () use ($eligibleStudents, $template, $exam, $user, $validated, $yearPrefix, &$nextSeq, &$count) {
                foreach ($eligibleStudents as $item) {
                    $student = $item['student'];
                    $data = $item['data'];

                    // Skip if this student already has a certificate for this exam+type (idempotent re-run).
                    $existing = IssuedCertificate::where('exam_id', $exam->id)
                        ->where('student_id', $student->id)
                        ->where('type', $validated['type'])
                        ->first();
                    if ($existing) continue;

                    // Generate a unique certificate number — retry with higher seq if collision.
                    $certNumber = null;
                    for ($tries = 0; $tries < 10; $tries++) {
                        $candidate = $yearPrefix . str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
                        $nextSeq++;
                        if (!IssuedCertificate::where('certificate_number', $candidate)->exists()) {
                            $certNumber = $candidate;
                            break;
                        }
                    }
                    if (!$certNumber) {
                        throw new \RuntimeException('Could not generate a unique certificate number after 10 tries.');
                    }

                    IssuedCertificate::create([
                        'exam_id' => $exam->id,
                        'student_id' => $student->id,
                        'certificate_template_id' => $template->id,
                        'issued_by' => $user->id,
                        'certificate_number' => $certNumber,
                        'type' => $validated['type'],
                        'data' => array_merge($data, [
                            'academic_session' => $exam->academicSession?->name ?? '',
                            'exam_name' => $exam->name,
                        ]),
                        'verification_code' => Str::random(32),
                        'issued_at' => now(),
                    ]);
                    $count++;
                }
            });
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to generate certificates: ' . $e->getMessage());
        }

        if ($count === 0) {
            return redirect()->route('certificates.index')->with('info', 'No new certificates issued (all eligible students already have one for this type).');
        }

        return redirect()->route('certificates.index')->with('success', "{$count} certificate(s) generated successfully.");
    }

    public function downloadCertificate(IssuedCertificate $certificate)
    {
        $certificate->load(['student.school.principal', 'student.schoolClass', 'student.section', 'template', 'exam.academicSession']);
        $data = $this->buildCertificateData($certificate);

        $school = $certificate->student->school;
        if ($school) {
            $school->principal_name = $school->principal?->name;
        }

        // Load the exam + examController so the certificate can show the
        // designated controller's name in the third signature column.
        $certificate->loadMissing('exam.examController:id,name');

        $pdf = Pdf::loadView('reports.certificate', [
            'template' => $certificate->template,
            'data' => $data,
            'school' => $school,
            'exam' => $certificate->exam,
        ])->setPaper('a4', $certificate->template->orientation);

        return $pdf->stream("certificate-{$certificate->certificate_number}.pdf");
    }

    public function downloadBulk(Exam $exam, Request $request)
    {
        $user = $request->user();
        $type = $request->input('type');

        $certificates = IssuedCertificate::where('exam_id', $exam->id)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->whereHas('student', fn ($q2) => $q2->where('school_id', $user->school_id)))
            ->with(['student.school', 'student.schoolClass', 'student.section', 'template', 'exam.academicSession'])
            ->get();

        if ($certificates->isEmpty()) {
            return redirect()->back()->with('error', 'No certificates to download.');
        }

        // Eager-load examController across the bulk to avoid N+1
        $certificates->loadMissing('exam.examController:id,name');

        $sheets = $certificates->map(function ($c) {
            $school = $c->student->school;
            if ($school) {
                $school->loadMissing('principal');
                $school->principal_name = $school->principal?->name;
            }
            return [
                'template' => $c->template,
                'data' => $this->buildCertificateData($c),
                'school' => $school,
                'exam' => $c->exam,
            ];
        });

        $orientation = $certificates->first()->template->orientation ?? 'landscape';

        $pdf = Pdf::loadView('reports.certificates-bulk', ['sheets' => $sheets])
            ->setPaper('a4', $orientation);

        return $pdf->stream("certificates-{$exam->slug}.pdf");
    }

    public function revoke(Request $request, IssuedCertificate $certificate): RedirectResponse
    {
        abort_unless($request->user()->can('certificates.revoke'), 403);
        try {
            $certificate->delete();
            return redirect()->back()->with('success', "Certificate {$certificate->certificate_number} revoked.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to revoke certificate: ' . $e->getMessage());
        }
    }

    /**
     * Public route — no auth. Verify authenticity via QR.
     */
    public function verify(string $code): Response
    {
        $cert = IssuedCertificate::where('verification_code', $code)
            ->with(['student:id,name,admission_no,school_id', 'student.school:id,name', 'exam:id,name', 'template:id,name,type'])
            ->first();

        return Inertia::render('Certificates/Verify', [
            'valid' => $cert !== null,
            'certificate' => $cert ? [
                'number' => $cert->certificate_number,
                'type' => $cert->type,
                'template_name' => $cert->template?->name,
                'student_name' => $cert->student?->name,
                'admission_no' => $cert->student?->admission_no,
                'school_name' => $cert->student?->school?->name,
                'exam_name' => $cert->exam?->name,
                'issued_at' => $cert->issued_at?->format('d M Y'),
            ] : null,
        ]);
    }

    // ========== HELPERS ==========

    protected function validateTemplate(Request $request): array
    {
        return $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:merit,subject_topper,pass,special_achievement,participation,custom',
            'title_text' => 'required|string|max:255',
            'body_text' => 'required|string',
            'primary_color' => 'required|string|max:20',
            'accent_color' => 'required|string|max:20',
            'orientation' => 'required|in:portrait,landscape',
            'border_style' => 'required|in:classic,modern,royal,minimal',
            'design_layout' => 'required|in:modern,gold,graduation,classic',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);
    }

    protected function getEligibleStudents(string $type, Exam $exam, $user, array $studentIds = []): \Illuminate\Support\Collection
    {
        $results = Result::where('exam_id', $exam->id)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with(['student'])
            ->get();

        return match ($type) {
            'merit' => $results->filter(fn ($r) => $r->position && $r->position <= 3 && $r->is_passed)
                ->map(fn ($r) => [
                    'student' => $r->student,
                    'data' => [
                        'rank' => (string) $r->position,
                        'percentage' => (string) $r->percentage,
                        'grade' => $r->grade ?? '',
                    ],
                ])->values(),

            'pass' => $results->filter(fn ($r) => $r->is_passed)
                ->map(fn ($r) => [
                    'student' => $r->student,
                    'data' => [
                        'percentage' => (string) $r->percentage,
                        'grade' => $r->grade ?? '',
                    ],
                ])->values(),

            'subject_topper' => $this->getSubjectToppers($exam, $user),

            'participation' => $results->map(fn ($r) => [
                'student' => $r->student,
                'data' => [
                    'percentage' => (string) $r->percentage,
                    'grade' => $r->grade ?? '',
                ],
            ])->values(),

            'custom', 'special_achievement' => Student::whereIn('id', $studentIds)->get()
                ->map(fn ($s) => ['student' => $s, 'data' => []])->values(),

            default => collect(),
        };
    }

    protected function getSubjectToppers(Exam $exam, $user): \Illuminate\Support\Collection
    {
        // For each subject, pick the highest scorer
        $results = Result::where('exam_id', $exam->id)
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->with('student')
            ->get();

        $toppers = collect();

        foreach ($results as $r) {
            $subjectResults = $r->subject_results ?? [];
            foreach ($subjectResults as $sr) {
                if ($sr['is_absent'] ?? false) continue;
                $subjectId = $sr['subject_id'] ?? null;
                if (!$subjectId) continue;

                $percentage = $sr['percentage'] ?? 0;
                $current = $toppers->get($subjectId);

                if (!$current || $percentage > ($current['data']['percentage_num'] ?? 0)) {
                    $toppers->put($subjectId, [
                        'student' => $r->student,
                        'data' => [
                            'subject_name' => $sr['subject_name'] ?? '',
                            'percentage' => (string) $percentage,
                            'percentage_num' => $percentage,
                            'grade' => $r->grade ?? '',
                        ],
                    ]);
                }
            }
        }

        return $toppers->values()->map(function ($item) {
            unset($item['data']['percentage_num']);
            return $item;
        });
    }

    protected function buildCertificateData(IssuedCertificate $cert): array
    {
        $student = $cert->student;
        $exam = $cert->exam;

        return [
            'student_name' => $student?->name ?? '',
            'rank' => $cert->data['rank'] ?? '',
            'percentage' => $cert->data['percentage'] ?? '',
            'grade' => $cert->data['grade'] ?? '',
            'exam_name' => $exam?->name ?? ($cert->data['exam_name'] ?? ''),
            'academic_session' => $cert->data['academic_session'] ?? ($exam?->academicSession?->name ?? ''),
            'school_name' => $student?->school?->name ?? '',
            'class_name' => $student?->schoolClass?->name ?? '',
            'section_name' => $student?->section?->name ?? '',
            'subject_name' => $cert->data['subject_name'] ?? '',
            'date' => $cert->issued_at?->format('d F Y') ?? '',
            'certificate_number' => $cert->certificate_number,
            'verification_code' => $cert->verification_code,
        ];
    }
}
