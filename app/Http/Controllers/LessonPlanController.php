<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Services\LessonPlanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Smart Lesson Plan — teachers enter a topic + subject + class and get a
 * structured, classroom-ready plan (AI-written when an Anthropic key is
 * configured, otherwise a solid offline template). Viewable + PDF.
 */
class LessonPlanController extends Controller
{
    public function __construct(protected LessonPlanService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('LessonPlan/Index', $this->formData($request) + [
            'plan' => null,
            'input' => null,
        ]);
    }

    public function generate(Request $request): Response
    {
        $v = $request->validate([
            'topic' => ['required', 'string', 'max:200'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'duration' => ['required', 'integer', 'min:15', 'max:240'],
            'medium' => ['nullable', 'string', 'max:40'],
            'level' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'language' => ['nullable', 'in:en,ur,both'],
        ]);

        // Teachers may only generate for subjects/classes assigned to them.
        $allow = $this->allowed($request->user());
        if (!$allow['unrestricted']) {
            abort_unless(
                in_array((int) $v['subject_id'], $allow['subject_ids'], true)
                && in_array((int) $v['school_class_id'], $allow['class_ids'], true),
                403,
                'You can only create lesson plans for your own subjects and assigned classes.'
            );
        }

        $subject = Subject::find($v['subject_id']);
        $class = SchoolClass::find($v['school_class_id']);

        $plan = $this->service->generate([
            'topic' => $v['topic'],
            'subject' => $subject?->name,
            'class' => $class?->name,
            'duration' => $v['duration'],
            'medium' => $v['medium'] ?? 'English',
            'level' => $v['level'] ?? 'mixed-ability',
            'notes' => $v['notes'] ?? '',
            'language' => $v['language'] ?? 'en',
            'school' => $request->user()->school?->name ?? '',
        ]);

        return Inertia::render('LessonPlan/Index', $this->formData($request) + [
            'plan' => $plan,
            'input' => $v + ['subject_name' => $subject?->name, 'class_name' => $class?->name],
        ]);
    }

    public function pdf(Request $request)
    {
        $v = $request->validate([
            'plan' => ['required', 'string'],
            'topic' => ['required', 'string', 'max:200'],
            'subject' => ['nullable', 'string', 'max:120'],
            'class' => ['nullable', 'string', 'max:120'],
            'section' => ['nullable', 'string', 'max:60'],
            'duration' => ['nullable', 'integer'],
            'students_count' => ['nullable', 'integer', 'min:0'],
            'lesson_date' => ['nullable', 'date'],
            'language' => ['nullable', 'in:en,ur,both'],
        ]);

        $plan = json_decode($v['plan'], true);
        abort_unless(is_array($plan) && !empty($plan['objectives']), 422, 'Invalid lesson plan payload.');

        $plan = $this->service->normaliseForPdf($plan, [
            'topic' => $v['topic'],
            'subject' => $v['subject'] ?? '',
            'class' => $v['class'] ?? '',
            'duration' => $v['duration'] ?? 40,
            'language' => $v['language'] ?? 'en',
        ]);

        // Detect Urdu so we can pick the right PDF engine. DomPDF can't shape
        // Arabic/Urdu glyphs (letters come out disconnected and reversed),
        // so we route Urdu plans through mPDF which has full RTL + shaping.
        $isUrdu = (($plan['language'] ?? 'en') === 'ur')
            || (!empty($plan['content']['is_urdu'] ?? false))
            || $this->looksUrdu($plan);

        $view = view('reports.lesson-plan', [
            'plan' => $plan,
            'school' => $request->user()->school,
            'teacher' => $request->user(),
            'section' => $v['section'] ?? '',
            'studentsCount' => $v['students_count'] ?? null,
            'lessonDate' => $v['lesson_date'] ? \Carbon\Carbon::parse($v['lesson_date']) : now(),
            'generatedAt' => now(),
            'isUrdu' => $isUrdu,
        ]);
        $html = $view->render();
        $slug = \Illuminate\Support\Str::slug(substr($v['topic'], 0, 40)) ?: 'lesson-plan';
        $filename = "lesson-plan-{$slug}.pdf";

        if ($isUrdu) {
            // mPDF — full Arabic/Urdu shaping + RTL bidi.
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 14, 'margin_right' => 14,
                'margin_top' => 12, 'margin_bottom' => 14,
                // Bundled fonts that ship with mPDF and render Urdu correctly.
                'default_font' => 'xbriyaz',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);
            $mpdf->SetDirectionality('rtl');

            // mPDF emits harmless E_WARNING from its internal table renderer
            // (Undefined array key 0 inside Mpdf.php:8601/8604/8607). The
            // PDF still renders correctly, but Laravel's debug handler
            // converts those warnings into ErrorException and aborts the
            // request. Suppress those non-fatal warnings for the render only.
            $oldLevel = error_reporting();
            error_reporting($oldLevel & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
            try {
                $mpdf->WriteHTML($html);
                $pdf = $mpdf->Output($filename, 'S');
            } finally {
                error_reporting($oldLevel);
            }

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        // English path stays on DomPDF — faster, simpler.
        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->stream($filename);
    }

    /**
     * Heuristic — does this plan contain Urdu text? Catches the case where
     * the form forgot to pass language=ur but the content was translated.
     */
    protected function looksUrdu(array $plan): bool
    {
        $sample = ($plan['content']['introduction'] ?? '') . ' '
            . ($plan['content']['definition'] ?? '') . ' '
            . (($plan['objectives'][0] ?? ''));
        return (bool) preg_match('/\p{Arabic}/u', $sample);
    }

    /**
     * Which subjects / classes this user may create plans for.
     * Admins (super / school) are unrestricted; teachers are limited to
     * their own SubjectTeacher assignments + the section they lead.
     */
    protected function allowed($user): array
    {
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            return ['unrestricted' => true, 'subject_ids' => [], 'class_ids' => []];
        }

        $assignments = SubjectTeacher::where('user_id', $user->id)
            ->where('is_active', true)
            ->get(['subject_id', 'school_class_id']);

        $classIds = $assignments->pluck('school_class_id')->all();
        $secClass = Section::where('class_teacher_id', $user->id)
            ->where('is_active', true)->pluck('school_class_id')->all();

        return [
            'unrestricted' => false,
            'subject_ids' => $assignments->pluck('subject_id')->unique()->values()->all(),
            'class_ids' => collect($classIds)->merge($secClass)->unique()->values()->all(),
        ];
    }

    // ─────────────────────── shared form data ───────────────────────

    protected function formData(Request $request): array
    {
        $user = $request->user();
        $allow = $this->allowed($user);

        $subjects = Subject::active()->ordered()
            ->when(!$allow['unrestricted'], fn ($q) => $q->whereIn('subjects.id', $allow['subject_ids'] ?: [0]))
            ->get(['id', 'name', 'code']);

        $classes = SchoolClass::query()
            ->where('is_active', true)
            ->when($user->isSchoolAdmin() && !$user->isSuperAdmin(), fn ($q) => $q->where('school_id', $user->school_id))
            ->when(!$allow['unrestricted'], fn ($q) => $q->whereIn('id', $allow['class_ids'] ?: [0]))
            ->ordered()
            ->get(['id', 'name']);

        // Pre-fill from what this teacher actually teaches.
        $defaultSubjectId = $subjects->count() === 1 ? $subjects->first()->id : null;
        $defaultClassId = $classes->count() === 1 ? $classes->first()->id : null;
        $sec = Section::where('class_teacher_id', $user->id)->where('is_active', true)->first();
        if ($sec && $classes->contains('id', $sec->school_class_id)) {
            $defaultClassId = $sec->school_class_id;
        }
        $st = SubjectTeacher::where('user_id', $user->id)->where('is_active', true)->first();
        if ($st) {
            $defaultSubjectId = $defaultSubjectId ?: $st->subject_id;
            $defaultClassId = $defaultClassId ?: $st->school_class_id;
        }

        return [
            'subjects' => $subjects,
            'classes' => $classes,
            'aiEnabled' => (bool) config('services.anthropic.key'),
            'defaults' => [
                'subject_id' => $defaultSubjectId,
                'school_class_id' => $defaultClassId,
            ],
        ];
    }
}
