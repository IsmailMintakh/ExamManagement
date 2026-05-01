<?php

namespace App\Http\Controllers;

use App\Models\FacultyMember;
use App\Models\GalleryAlbum;
use App\Models\HeroSlide;
use App\Models\ContactMessage;
use App\Models\NewsArticle;
use App\Models\PageBlock;
use App\Models\PageMeta;
use App\Models\School;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    /**
     * Public ID card verification — anyone can scan the QR or enter an admission no.
     * No auth required.
     */
    public function verifyStudent(string $admission): Response
    {
        $student = Student::with(['school:id,name,address', 'schoolClass:id,name', 'section:id,name', 'academicSession:id,name'])
            ->where('admission_no', $admission)
            ->first();

        return Inertia::render('Public/VerifyStudent', [
            'valid' => $student !== null,
            'student' => $student ? [
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'class_name' => $student->schoolClass?->name,
                'section_name' => $student->section?->name,
                'school_name' => $student->school?->name,
                'school_address' => $student->school?->address,
                'session' => $student->academicSession?->name,
                'status' => $student->status,
                'gender' => $student->gender,
            ] : null,
        ]);
    }

    public function home(): Response
    {
        $settings = SiteSetting::allCached();
        $slides = HeroSlide::active()->get(['id', 'eyebrow', 'title', 'subtitle', 'description',
            'image_path', 'cta_label', 'cta_url', 'cta_secondary_label', 'cta_secondary_url',
            'overlay_color', 'overlay_opacity']);

        // Featured + recent news for the homepage news strip
        $latestNews = NewsArticle::published()
            ->limit(3)
            ->get(['id', 'title', 'slug', 'category', 'excerpt', 'image_path', 'image_gradient', 'published_at']);

        return Inertia::render('Public/Home', [
            'site'       => $settings,
            'slides'     => $slides,
            'liveStats'  => $this->liveStats($settings),
            'latestNews' => $latestNews,
            'blocks'     => PageBlock::forPage('home')->active()->get(),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Public/About', [
            'site'   => SiteSetting::allCached(),
            'hero'   => PageMeta::forPage('about'),
            'blocks' => PageBlock::forPage('about')->active()->get(),
        ]);
    }

    public function schools(): Response
    {
        return Inertia::render('Public/Schools');
    }

    public function academics(): Response
    {
        return Inertia::render('Public/Academics', [
            'site'   => SiteSetting::allCached(),
            'hero'   => PageMeta::forPage('academics'),
            'blocks' => PageBlock::forPage('academics')->active()->get(),
        ]);
    }

    public function admissions(): Response
    {
        return Inertia::render('Public/Admissions', [
            'site'   => SiteSetting::allCached(),
            'hero'   => PageMeta::forPage('admissions'),
            'blocks' => PageBlock::forPage('admissions')->active()->get(),
        ]);
    }

    public function faculty(): Response
    {
        $members = FacultyMember::active()->get();

        return Inertia::render('Public/Faculty', [
            'site'      => SiteSetting::allCached(),
            'hero'      => PageMeta::forPage('faculty'),
            'members'   => $members,
            'principal' => $members->firstWhere('is_principal', true),
            'blocks'    => PageBlock::forPage('faculty')->active()->get(),
        ]);
    }

    public function gallery(): Response
    {
        return Inertia::render('Public/Gallery', [
            'site'   => SiteSetting::allCached(),
            'hero'   => PageMeta::forPage('gallery'),
            'albums' => GalleryAlbum::active()->withCount('photos')->get(),
            'blocks' => PageBlock::forPage('gallery')->active()->get(),
        ]);
    }

    public function galleryShow(string $slug): Response
    {
        $album = GalleryAlbum::where('slug', $slug)->where('is_active', true)->firstOrFail();
        return Inertia::render('Public/GalleryShow', [
            'site'   => SiteSetting::allCached(),
            'album'  => $album,
            'photos' => $album->photos()->get(),
        ]);
    }

    public function news(Request $request): Response
    {
        $query = NewsArticle::published();
        if ($request->filled('category') && $request->string('category')->value() !== 'All') {
            $query->where('category', $request->string('category'));
        }

        $articles = $query->paginate(9)->withQueryString();
        $featured = NewsArticle::published()->featured()->first();

        // Distinct categories of published articles (raw query — published()
        // scope adds ORDER BY published_at which conflicts with DISTINCT)
        $categories = NewsArticle::query()
            ->where('is_published', true)
            ->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Public/News', [
            'site'       => SiteSetting::allCached(),
            'hero'       => PageMeta::forPage('news'),
            'articles'   => $articles,
            'featured'   => $featured,
            'categories' => $categories,
            'filter'     => $request->string('category')->value() ?: 'All',
            'blocks'     => PageBlock::forPage('news')->active()->get(),
        ]);
    }

    public function newsShow(string $slug): Response
    {
        $article = NewsArticle::published()->where('slug', $slug)->firstOrFail();
        // Increment view counter (silent)
        $article->increment('view_count');

        $related = NewsArticle::published()
            ->where('id', '!=', $article->id)
            ->where('category', $article->category)
            ->limit(3)
            ->get(['id', 'title', 'slug', 'category', 'excerpt', 'image_path', 'image_gradient', 'published_at']);

        return Inertia::render('Public/NewsShow', [
            'site'    => SiteSetting::allCached(),
            'article' => $article,
            'related' => $related,
        ]);
    }

    public function results(): Response
    {
        return Inertia::render('Public/Results', [
            'site'   => SiteSetting::allCached(),
            'hero'   => PageMeta::forPage('results'),
            'blocks' => PageBlock::forPage('results')->active()->get(),
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact', [
            'site'   => SiteSetting::allCached(),
            'hero'   => PageMeta::forPage('contact'),
            'blocks' => PageBlock::forPage('contact')->active()->get(),
        ]);
    }

    /**
     * Public contact form submission. Rate-limited per IP, honeypot-protected.
     */
    public function submitContact(Request $request): RedirectResponse
    {
        // Honeypot — bots fill hidden fields
        if (filled($request->input('website'))) {
            return back()->with('success', 'Message sent.');
        }

        // Rate limit: 5 submissions per hour per IP
        $key = 'contact-form:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withInput()
                ->withErrors(['message' => 'Too many messages from your address. Please try again later.']);
        }
        RateLimiter::hit($key, 3600);

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email', 'max:120'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        ContactMessage::create($data + [
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 400),
        ]);

        return back()->with('success', 'Thank you — your message has been sent. We\'ll respond within 1–2 business days.');
    }

    /**
     * Live counts pulled from the DB. Admin overrides (set in Site Settings)
     * take precedence — if non-empty, the admin's number wins; otherwise we
     * use the actual count.
     */
    private function liveStats(array $settings): array
    {
        $studentsOverride = $settings['stat_students_override'] ?? null;
        $teachersOverride = $settings['stat_teachers_override'] ?? null;

        $studentsCount = ($studentsOverride && (int) $studentsOverride > 0)
            ? (int) $studentsOverride
            : Student::query()->where('status', 'active')->count();

        $teachersCount = ($teachersOverride && (int) $teachersOverride > 0)
            ? (int) $teachersOverride
            : User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['class-teacher', 'subject-teacher']))
                ->count();

        return [
            'students'    => $studentsCount,
            'teachers'    => $teachersCount,
            'pass_rate'   => (float) ($settings['stat_pass_rate'] ?? 0),
            'years_legacy' => (int) ($settings['stat_years_legacy'] ?? 0),
            'schools'     => School::query()->where('is_active', true)->count(),
        ];
    }
}
