<?php

use App\Http\Controllers\AcademicSessionController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AnalyticsInsightsController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ClassTeacherController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamSchedulingController;
use App\Http\Controllers\ExamTypeController;
use App\Http\Controllers\GradingScaleController;
use App\Http\Controllers\MarksController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperGeneratorController;
use App\Http\Controllers\FamilyPortalController;
use App\Http\Controllers\ParentPortalController;
use App\Http\Controllers\PublicResultLookupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResultCardTemplateController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubstitutionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentTransferController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SupplementaryController;
use App\Models\StudentTransfer;
use App\Http\Controllers\TeacherAssignmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Offline fallback page (rendered by service worker when both network
// and cache miss). No auth so it works even before login.
Route::get('/offline', fn () => Inertia::render('Offline'))->name('offline');

// Public result lookup — no auth needed. Admission number + DOB shows
// only published results. IP rate-limited inside the controller to stop
// admission-number scraping.
Route::get('/check-result', [PublicResultLookupController::class, 'index'])->name('public.result-lookup');
Route::post('/check-result', [PublicResultLookupController::class, 'lookup'])->name('public.result-lookup.search');

/**
 * Bulletproof file delivery for the public storage disk.
 *
 * Bypasses the public/storage symlink — useful on shared hosts where the
 * symlink is broken, gives 403, or gets reset by the platform. Files
 * served with strong cache headers since their hashed filenames make them
 * effectively immutable.
 *
 * BOTH /uploads/* and /storage/* are wired so legacy hardcoded URLs in
 * Vue templates continue to work even when the symlink is missing on the
 * production host. The handler is the same.
 */
$serveStorage = function (string $path) {
    $path = ltrim($path, '/');
    // Disallow path traversal — reject anything containing ..
    if (str_contains($path, '..')) abort(404);

    $full = storage_path('app/public/' . $path);
    if (!is_file($full)) abort(404);

    return response()->file($full, [
        'Cache-Control' => 'public, max-age=2592000, immutable',  // 30 days
    ]);
};
Route::get('/uploads/{path}', $serveStorage)->where('path', '.*')->name('uploads');
// Fallback: the same handler under /storage/* so older hardcoded URLs work
// even when the public/storage symlink is broken (common on shared hosting).
// Route registers ONLY if the symlink is missing OR an env override forces
// it on, so dev machines with a working symlink keep using the symlink.
Route::get('/storage/{path}', $serveStorage)->where('path', '.*')->name('storage-fallback');

// Public website (marketing/info pages — no auth required)
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/academics', [PublicController::class, 'academics'])->name('public.academics');
Route::get('/admissions', [PublicController::class, 'admissions'])->name('public.admissions');
Route::get('/faculty', [PublicController::class, 'faculty'])->name('public.faculty');
Route::get('/gallery', [PublicController::class, 'gallery'])->name('public.gallery');
Route::get('/news', [PublicController::class, 'news'])->name('public.news');
Route::get('/news/{slug}', [PublicController::class, 'newsShow'])->name('public.news.show');
Route::get('/gallery/{slug}', [PublicController::class, 'galleryShow'])->name('public.gallery.show');
Route::get('/board-results', [PublicController::class, 'results'])->name('public.results');
Route::get('/contact', [PublicController::class, 'contact'])->name('public.contact');
Route::post('/contact', [PublicController::class, 'submitContact'])->name('public.contact.submit');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Global Search (command palette)
    Route::get('search', [SearchController::class, 'search'])->name('search');
    Route::get('search/actions', [SearchController::class, 'quickActions'])->name('search.actions');

    // Class Teacher hub ("My Class" — see students, marks status, results for own section)
    Route::get('my-class', [ClassTeacherController::class, 'index'])->name('class-teacher.index');

    // "My Subjects" — every (subject, class, section) the teacher is assigned to
    // for the current session, with student counts + marks progress per row.
    Route::get('my-subjects', [\App\Http\Controllers\MySubjectsController::class, 'index'])
        ->name('my-subjects.index');

    // Primary-section Assessment marks — overall 10-mark conduct/participation
    // score entered ONCE per student per session by the class teacher of an
    // ECD-5 section. Feeds the primary Annual Result aggregator.
    Route::get('assessment', [\App\Http\Controllers\AssessmentController::class, 'index'])
        ->name('assessment.index');
    Route::post('assessment', [\App\Http\Controllers\AssessmentController::class, 'store'])
        ->name('assessment.store');

    // ─── Timetable + substitution ───
    Route::prefix('timetable')->name('timetable.')->group(function () {
        Route::get('/', [TimetableController::class, 'index'])->name('index');
        Route::get('setup', [TimetableController::class, 'setup'])->name('setup');
        Route::post('setup', [TimetableController::class, 'saveSetup'])->name('setup.save');
        Route::get('builder/{section}', [TimetableController::class, 'builder'])->name('builder');
        Route::post('builder/{section}', [TimetableController::class, 'saveBuilder'])->name('builder.save');
        Route::get('section/{section}', [TimetableController::class, 'sectionView'])->name('section');
        Route::get('section/{section}/pdf', [TimetableController::class, 'sectionPdf'])->name('section.pdf');
        Route::get('teacher/{user}', [TimetableController::class, 'teacherView'])->name('teacher');
        Route::get('teacher/{user}/pdf', [TimetableController::class, 'teacherPdf'])->name('teacher.pdf');
        Route::get('school/pdf', [TimetableController::class, 'schoolPdf'])->name('school.pdf');
        Route::get('master', [TimetableController::class, 'master'])->name('master');
        Route::get('master/pdf', [TimetableController::class, 'masterPdf'])->name('master.pdf');
        Route::get('routine/pdf', [TimetableController::class, 'routinePdf'])->name('routine.pdf');

        // P5 — Reports
        Route::get('reports', [\App\Http\Controllers\TimetableReportController::class, 'index'])->name('reports');

        // P2 — Workflow actions
        Route::post('section/{section}/lock', [TimetableController::class, 'lockSection'])->name('section.lock');
        Route::post('section/{section}/unlock', [TimetableController::class, 'unlockSection'])->name('section.unlock');
        Route::post('section/{target}/copy-from/{source}', [TimetableController::class, 'copyFrom'])->name('section.copy');
        Route::post('swap', [TimetableController::class, 'swap'])->name('swap');
        Route::post('generate', [TimetableController::class, 'generate'])->name('generate');
        Route::post('setup/preset', [TimetableController::class, 'applyPreset'])->name('setup.preset');

        // Daily absence + class adjustments (auto-cover)
        Route::get('adjustments', [SubstitutionController::class, 'index'])->name('adjustments');
        Route::post('adjustments/absences', [SubstitutionController::class, 'toggleAbsence'])->name('adjustments.absence');
        Route::post('adjustments/generate', [SubstitutionController::class, 'generate'])->name('adjustments.generate');
        Route::post('adjustments/{assignment}/confirm', [SubstitutionController::class, 'confirm'])->name('adjustments.confirm');
        Route::post('adjustments/{assignment}/reassign', [SubstitutionController::class, 'reassign'])->name('adjustments.reassign');
        Route::post('adjustments/{assignment}/decline', [SubstitutionController::class, 'decline'])->name('adjustments.decline');
        Route::get('adjustments/slip', [SubstitutionController::class, 'slip'])->name('adjustments.slip');
        Route::post('adjustments/policy', [SubstitutionController::class, 'updatePolicy'])->name('adjustments.policy');
    });

    // Personal class-adjustment inbox for any teacher.
    Route::get('/my-adjustments', [\App\Http\Controllers\MyCoversController::class, 'index'])->name('my-adjustments');
    Route::post('/my-adjustments/{assignment}/decline', [\App\Http\Controllers\MyCoversController::class, 'decline'])->name('my-adjustments.decline');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/signature', [ProfileController::class, 'uploadSignature'])->name('profile.signature.upload');
    Route::delete('/profile/signature', [ProfileController::class, 'deleteSignature'])->name('profile.signature.delete');

    // Academic Sessions
    Route::resource('academic-sessions', AcademicSessionController::class);
    Route::post('academic-sessions/{academic_session}/switch', [AcademicSessionController::class, 'switchSession'])->name('academic-sessions.switch');

    // Schools (Super Admin)
    Route::resource('schools', SchoolController::class);

    // Subjects (Super Admin manages, others view)
    Route::resource('subjects', SubjectController::class);

    // Classes
    Route::resource('classes', SchoolClassController::class)->parameters(['classes' => 'schoolClass']);

    // Sections
    Route::patch('sections/{section}/class-teacher', [SectionController::class, 'updateClassTeacher'])
        ->name('sections.class-teacher.update');
    Route::resource('sections', SectionController::class);

    // Students
    Route::post('students/bulk-delete', [StudentController::class, 'bulkDelete'])->name('students.bulk-delete');
    Route::post('students/bulk-export', [StudentController::class, 'bulkExport'])->name('students.bulk-export');
    Route::post('students/bulk-id-cards', [StudentController::class, 'bulkIdCards'])->name('students.bulk-id-cards');
    Route::get('students/{student}/id-card', [StudentController::class, 'idCard'])->name('students.id-card');
    // Smart-default suggestions for the create form (next admission/roll no).
    // GET so the frontend can call it without CSRF — read-only suggestion endpoint.
    Route::get('students/smart-defaults', [StudentController::class, 'smartDefaults'])->name('students.smart-defaults');
    Route::resource('students', StudentController::class);
    Route::get('students-import', [StudentController::class, 'import'])->name('students.import');
    Route::post('students-import', [StudentController::class, 'processImport'])->name('students.process-import');

    // Exam Types
    Route::resource('exam-types', ExamTypeController::class);

    // Exams
    Route::post('exams/bulk-delete', [ExamController::class, 'bulkDelete'])->name('exams.bulk-delete');
    Route::resource('exams', ExamController::class);
    Route::post('exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
    Route::post('exams/{exam}/lock', [ExamController::class, 'lock'])->name('exams.lock');
    Route::post('exams/{exam}/cleanup-orphan-subjects', [ExamController::class, 'cleanupOrphanSubjects'])->name('exams.cleanup-orphans');
    // Append-only endpoint backing the "Add Missing Subject" modal — see
    // ExamController::addMissingSubjects for the safety guarantees.
    Route::post('exams/{exam}/add-missing-subjects', [ExamController::class, 'addMissingSubjects'])->name('exams.add-missing-subjects');
    // Inline "Update Marks" endpoint — corrects total / passing marks for one
    // or more (subject, class) tuples without going through the full wizard,
    // and re-runs the result cascade for affected sections.
    Route::post('exams/{exam}/update-subject-marks', [ExamController::class, 'updateSubjectMarks'])->name('exams.update-subject-marks');
    // Inline "Save edit policy" endpoint — flips the per-exam post-submission
    // edit policy + scope without walking the wizard.
    Route::post('exams/{exam}/update-edit-policy', [ExamController::class, 'updateEditPolicy'])->name('exams.update-edit-policy');
    // Destructive recovery — drop a class entirely from this exam plus its
    // dependent marks/submissions/results. For mistakenly-added classes.
    Route::post('exams/{exam}/remove-class', [ExamController::class, 'removeClass'])->name('exams.remove-class');
    // Per-subject destructive remove — password-gated when marks exist.
    Route::post('exams/{exam}/remove-subject', [ExamController::class, 'removeSubject'])->name('exams.remove-subject');
    // Recovery — restore every soft-deleted Mark for an exam (admin only).
    Route::post('exams/{exam}/restore-deleted-marks', [ExamController::class, 'restoreDeletedMarks'])->name('exams.restore-deleted-marks');

    // GET → edit redirects for the POST-only destructive/inline endpoints
    // above. Without these, landing on the URL directly (typed in the
    // address bar, opened from a stale tab, session-expired XHR retry)
    // throws a noisy 405. Sending the user back to the exam editor is the
    // right behaviour — the destructive actions are admin-only UI buttons.
    foreach ([
        'exams/{exam}/remove-class',
        'exams/{exam}/remove-subject',
        'exams/{exam}/update-edit-policy',
        'exams/{exam}/update-subject-marks',
        'exams/{exam}/add-missing-subjects',
    ] as $postOnlyUrl) {
        Route::get($postOnlyUrl, fn (\App\Models\Exam $exam) =>
            redirect()->route('exams.edit', $exam));
    }

    // Marks Entry
    Route::get('marks', [MarksController::class, 'index'])->name('marks.index');
    Route::get('marks/progress', [\App\Http\Controllers\MarksProgressController::class, 'index'])->name('marks.progress');
    Route::get('marks/{exam}/entry/{subject}/{section}', [MarksController::class, 'entry'])->name('marks.entry');
    Route::post('marks/{exam}/store', [MarksController::class, 'store'])->name('marks.store');
    Route::post('marks/{exam}/autosave', [MarksController::class, 'autosave'])->name('marks.autosave');
    Route::post('marks/{exam}/submit/{subject}/{section}', [MarksController::class, 'submit'])->name('marks.submit');

    // Results
    Route::get('results', [ResultController::class, 'index'])->name('results.index');
    Route::get('result-review', [ResultController::class, 'reviewQueue'])->name('results.review-queue');
    Route::get('results/{exam}/generate', [ResultController::class, 'generate'])->name('results.generate');
    Route::post('results/{exam}/process', [ResultController::class, 'processGenerate'])->name('results.process');
    Route::get('results/{exam}/section/{section}', [ResultController::class, 'show'])->name('results.show');
    Route::post('results/{exam}/submit-to-ddo', [ResultController::class, 'submitToDdo'])->name('results.submit-to-ddo');
    Route::post('results/{exam}/finalize/{school}', [ResultController::class, 'finalize'])->name('results.finalize');
    Route::post('result-submissions/{submission}/approve', [ResultController::class, 'approve'])->name('result-submissions.approve');
    Route::post('result-submissions/{submission}/return', [ResultController::class, 'returnForCorrection'])->name('result-submissions.return');
    // Result publishing — gates Family Portal & public lookup visibility
    Route::post('results/{exam}/publish', [ResultController::class, 'publishResults'])->name('results.publish');
    Route::post('results/{exam}/unpublish', [ResultController::class, 'unpublishResults'])->name('results.unpublish');
    // Result correction — logs an amendment row and re-notifies the student/parent
    Route::post('results/{result}/amend', [ResultController::class, 'amendResult'])->name('results.amend');

    // Analytics (DDO insights)
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Analytics Insights (advanced dashboards)
    Route::prefix('insights')->name('insights.')->group(function () {
        Route::get('student-progress', [AnalyticsInsightsController::class, 'studentProgress'])->name('student-progress');
        Route::get('at-risk', [AnalyticsInsightsController::class, 'atRiskStudents'])->name('at-risk');
        Route::get('subject-heatmap', [AnalyticsInsightsController::class, 'subjectHeatmap'])->name('subject-heatmap');
        Route::get('teacher-performance', [AnalyticsInsightsController::class, 'teacherPerformance'])->name('teacher-performance');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('award-list/{exam}', [ReportController::class, 'awardList'])->name('award-list');
        Route::get('result-sheet/{exam}/{schoolClass}', [ReportController::class, 'resultSheet'])->name('result-sheet');
        Route::get('report-card/{exam}/{student}', [ReportController::class, 'reportCard'])->name('report-card');
        Route::get('section-mark-sheets/{exam}/{section}', [ReportController::class, 'sectionMarkSheets'])->name('section-mark-sheets');
        // Bulk: every student in the exam (or one class via ?school_class_id) in one stacked PDF.
        Route::get('bulk-mark-sheets/{exam}', [ReportController::class, 'bulkMarkSheets'])->name('bulk-mark-sheets');
        // Bulk: result sheet for every class in one PDF, page-break between classes.
        Route::get('bulk-result-sheets/{exam}', [ReportController::class, 'bulkResultSheets'])->name('bulk-result-sheets');
        Route::get('attendance-sheet/{exam}/{schoolClass}', [ReportController::class, 'attendanceSheet'])->name('attendance-sheet');
        Route::get('progress-report/{student}', [ReportController::class, 'progressReport'])->name('progress-report');
        Route::get('export/{type}/{exam}', [ReportController::class, 'exportExcel'])->name('export');

        // ── Analytics & per-teacher reports (web pages + bulk PDFs) ──
        Route::get('exam-analytics/{exam}', [ReportController::class, 'examAnalytics'])->name('exam-analytics');
        Route::get('teacher-report-card/{user}', [ReportController::class, 'teacherReportCard'])->name('teacher-report-card');
        Route::get('progress-booklet/{student}', [ReportController::class, 'progressBooklet'])->name('progress-booklet');
        Route::get('progress-booklet-bulk/{section}', [ReportController::class, 'progressBookletBulk'])->name('progress-booklet-bulk');
    });

    // Users
    Route::resource('users', UserController::class);

    // Teacher Assignments
    Route::post('teacher-assignments/bulk', [TeacherAssignmentController::class, 'bulkStore'])->name('teacher-assignments.bulk');
    Route::get('teacher-assignments/current/{user}', [TeacherAssignmentController::class, 'current'])->name('teacher-assignments.current');
    Route::resource('teacher-assignments', TeacherAssignmentController::class)->only(['index', 'store', 'destroy']);

    // Grading Scales
    Route::resource('grading-scales', GradingScaleController::class);

    // Result Card Templates (DDO only)
    Route::post('result-card-templates/preview', [ResultCardTemplateController::class, 'preview'])->name('result-card-templates.preview');
    Route::resource('result-card-templates', ResultCardTemplateController::class)->parameters(['result-card-templates' => 'resultCardTemplate']);

    // Roles & Permissions (DDO only)
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/assign-users', [RoleController::class, 'assignUsers'])->name('roles.assign-users');
    Route::post('roles/users/{user}/sync', [RoleController::class, 'syncUserRoles'])->name('roles.sync-user');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.update-permissions');

    // Help / Documentation
    Route::get('help', fn () => \Inertia\Inertia::render('Help'))->name('help');

    // Web Push (browser notifications)
    Route::prefix('push')->name('push.')->group(function () {
        Route::get('public-key', [\App\Http\Controllers\PushController::class, 'publicKey'])->name('public-key');
        Route::post('subscribe',  [\App\Http\Controllers\PushController::class, 'subscribe'])->name('subscribe');
        Route::post('unsubscribe',[\App\Http\Controllers\PushController::class, 'unsubscribe'])->name('unsubscribe');
        Route::post('test',       [\App\Http\Controllers\PushController::class, 'sendTest'])->name('test');
    });

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // DDO "viewing school" selector — super-admin picks a school from the
    // topbar to scope dashboards/lists down to that school. Stored in
    // session so it persists across pages. Pass school_id=null/"all" to
    // clear the filter (i.e. view across every school again).
    Route::post('viewing-school', function (\Illuminate\Http\Request $request) {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);
        $id = $request->input('school_id');
        if ($id) {
            session(['viewing_school_id' => (int) $id]);
        } else {
            session()->forget('viewing_school_id');
        }
        return back();
    })->name('viewing-school.set');

    // Activity Log
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    // Danger Zone — destructive data cleanup (super-admin only, password-protected)
    Route::prefix('admin/data-cleanup')->name('admin.data-cleanup.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DataCleanupController::class, 'index'])->name('index');
        Route::delete('exam/{exam}', [\App\Http\Controllers\Admin\DataCleanupController::class, 'destroyExam'])->name('destroy-exam');
        Route::post('wipe-exams', [\App\Http\Controllers\Admin\DataCleanupController::class, 'wipeAllExams'])->name('wipe-exams');
        Route::post('wipe-results', [\App\Http\Controllers\Admin\DataCleanupController::class, 'wipeAllResults'])->name('wipe-results');
        Route::post('wipe-marks', [\App\Http\Controllers\Admin\DataCleanupController::class, 'wipeAllMarks'])->name('wipe-marks');
        Route::post('wipe-datesheets', [\App\Http\Controllers\Admin\DataCleanupController::class, 'wipeAllDatesheets'])->name('wipe-datesheets');
        Route::post('wipe-timetable', [\App\Http\Controllers\Admin\DataCleanupController::class, 'wipeAllTimetable'])->name('wipe-timetable');
    });

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');

    // Public Website CMS (DDO only — site settings + hero slider + news + gallery + faculty)
    Route::prefix('website')->name('website.')->middleware('can:website.manage')->group(function () {
        Route::get('settings', [\App\Http\Controllers\Website\WebsiteSettingsController::class, 'edit'])->name('settings');
        Route::post('settings', [\App\Http\Controllers\Website\WebsiteSettingsController::class, 'update'])->name('settings.update');

        Route::post('hero-slides/reorder', [\App\Http\Controllers\Website\HeroSlideController::class, 'reorder'])->name('hero-slides.reorder');
        Route::resource('hero-slides', \App\Http\Controllers\Website\HeroSlideController::class)
            ->parameters(['hero-slides' => 'heroSlide'])
            ->except(['show']);

        // News articles
        Route::resource('news', \App\Http\Controllers\Website\NewsArticleController::class)
            ->parameters(['news' => 'news'])
            ->except(['show']);

        // Gallery albums + nested photo actions
        Route::resource('gallery', \App\Http\Controllers\Website\GalleryAlbumController::class)
            ->parameters(['gallery' => 'album'])
            ->except(['show']);
        Route::post('gallery/{album}/photos', [\App\Http\Controllers\Website\GalleryAlbumController::class, 'uploadPhotos'])->name('gallery.photos.upload');
        Route::put('gallery/{album}/photos/{photo}', [\App\Http\Controllers\Website\GalleryAlbumController::class, 'updatePhoto'])->name('gallery.photos.update');
        Route::delete('gallery/{album}/photos/{photo}', [\App\Http\Controllers\Website\GalleryAlbumController::class, 'deletePhoto'])->name('gallery.photos.destroy');

        // Faculty
        Route::resource('faculty', \App\Http\Controllers\Website\FacultyMemberController::class)
            ->parameters(['faculty' => 'faculty'])
            ->except(['show']);

        // Page Blocks (About, Academics, Admissions content)
        Route::get('pages', [\App\Http\Controllers\Website\PageBlockController::class, 'index'])->name('pages.index');
        Route::get('pages/{key}', [\App\Http\Controllers\Website\PageBlockController::class, 'show'])->name('pages.show');
        Route::put('pages/{key}/hero', [\App\Http\Controllers\Website\PageBlockController::class, 'updateHero'])->name('pages.hero.update');
        Route::post('pages/{key}/reorder', [\App\Http\Controllers\Website\PageBlockController::class, 'reorder'])->name('pages.reorder');
        Route::post('page-blocks', [\App\Http\Controllers\Website\PageBlockController::class, 'store'])->name('page-blocks.store');
        Route::put('page-blocks/{block}', [\App\Http\Controllers\Website\PageBlockController::class, 'update'])->name('page-blocks.update');
        Route::delete('page-blocks/{block}', [\App\Http\Controllers\Website\PageBlockController::class, 'destroy'])->name('page-blocks.destroy');

        // Contact Messages
        Route::get('contact-messages', [\App\Http\Controllers\Website\ContactMessageController::class, 'index'])->name('contact-messages.index');
        Route::get('contact-messages/{message}', [\App\Http\Controllers\Website\ContactMessageController::class, 'show'])->name('contact-messages.show');
        Route::put('contact-messages/{message}', [\App\Http\Controllers\Website\ContactMessageController::class, 'update'])->name('contact-messages.update');
        Route::delete('contact-messages/{message}', [\App\Http\Controllers\Website\ContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
        Route::post('contact-messages/bulk', [\App\Http\Controllers\Website\ContactMessageController::class, 'bulk'])->name('contact-messages.bulk');
    });

    // Student Promotion (year-end workflow)
    Route::prefix('promotion')->name('promotion.')->group(function () {
        Route::get('/', [PromotionController::class, 'index'])->name('index');
        Route::get('classes/{class}', [PromotionController::class, 'show'])->name('show');
        Route::post('classes/{class}/process', [PromotionController::class, 'process'])->name('process');
    });

    // Multi-Year Academic Archive
    Route::prefix('archive')->name('archive.')->group(function () {
        Route::get('/', [ArchiveController::class, 'index'])->name('index');
        Route::get('sessions/{session}', [ArchiveController::class, 'show'])->name('show')->scopeBindings();
    });

    // Supplementary Exam workflow
    Route::prefix('supplementary')->name('supplementary.')->group(function () {
        Route::get('/', [SupplementaryController::class, 'index'])->name('index');
        Route::get('exams/{exam}', [SupplementaryController::class, 'show'])->name('show');
        Route::post('exams/{exam}/mark-eligible', [SupplementaryController::class, 'markEligible'])->name('mark-eligible');
        Route::get('results/{result}/entry', [SupplementaryController::class, 'enterMarks'])->name('entry');
        Route::post('results/{result}/marks', [SupplementaryController::class, 'storeMarks'])->name('store-marks');
        Route::post('results/{result}/finalize', [SupplementaryController::class, 'finalize'])->name('finalize');
    });

    // Student Transfers (intra/inter-school)
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('/', [StudentTransferController::class, 'index'])->name('index');
        Route::get('students/{student}/create', [StudentTransferController::class, 'create'])->name('create');
        Route::post('/', [StudentTransferController::class, 'store'])->name('store');
        Route::post('{transfer}/approve', [StudentTransferController::class, 'approve'])->name('approve');
        Route::post('{transfer}/complete', [StudentTransferController::class, 'complete'])->name('complete');
        Route::post('{transfer}/reject', [StudentTransferController::class, 'reject'])->name('reject');
    });

    // Exam Scheduling (Date Sheet, Rooms, Seating, Invigilators, Admit Cards)
    // All read routes need 'scheduling.view'; write routes additionally need
    // 'scheduling.manage'. Auth was originally in the controller constructor
    // but Laravel 11+ removed that pattern — do it here at the route level.
    Route::prefix('scheduling')->name('scheduling.')->middleware('can:scheduling.view')->group(function () {
        // Read endpoints (covered by 'can:scheduling.view' on the group)
        Route::get('/', [ExamSchedulingController::class, 'examsList'])->name('exams');
        Route::get('exams/{exam}', [ExamSchedulingController::class, 'index'])->name('index');
        Route::get('exams/{exam}/datesheet', [ExamSchedulingController::class, 'datesheet'])->name('datesheet');
        Route::get('exams/{exam}/datesheet/pdf', [ExamSchedulingController::class, 'datesheetPdf'])->name('datesheet-pdf');
        Route::get('rooms', [ExamSchedulingController::class, 'rooms'])->name('rooms');
        Route::get('exams/{exam}/seating/{section}', [ExamSchedulingController::class, 'seating'])->name('seating');
        Route::get('exams/{exam}/seating/room/{room}/pdf', [ExamSchedulingController::class, 'seatingPdf'])->name('seating-pdf');
        Route::get('exams/{exam}/invigilators', [ExamSchedulingController::class, 'invigilators'])->name('invigilators');
        Route::get('exams/{exam}/invigilators/pdf', [ExamSchedulingController::class, 'invigilatorDutyPdf'])->name('invigilator-pdf');
        Route::get('exams/{exam}/admit-cards', [ExamSchedulingController::class, 'admitCards'])->name('admit-cards');
        Route::get('exams/{exam}/admit-cards/download', [ExamSchedulingController::class, 'downloadAdmitCards'])->name('admit-cards-download');
        // Bulk: every student in the exam in one PDF (optionally filtered by ?school_class_id)
        Route::get('exams/{exam}/admit-cards/bulk', [ExamSchedulingController::class, 'downloadBulkAdmitCards'])->name('admit-cards-bulk');

        // Write endpoints — require additional 'scheduling.manage' permission
        Route::middleware('can:scheduling.manage')->group(function () {
            Route::post('exams/{exam}/datesheet', [ExamSchedulingController::class, 'storeSchedule'])->name('store-schedule');
            Route::post('exams/{exam}/datesheet/auto', [ExamSchedulingController::class, 'autoGenerateDatesheet'])->name('datesheet-auto');
            Route::post('rooms', [ExamSchedulingController::class, 'storeRoom'])->name('store-room');
            Route::post('exams/{exam}/seating/{section}/auto', [ExamSchedulingController::class, 'autoAssignSeats'])->name('auto-seats');
            Route::post('exams/{exam}/seating/{section}/clear', [ExamSchedulingController::class, 'clearSeats'])->name('clear-seats');
            Route::post('exams/{exam}/invigilators', [ExamSchedulingController::class, 'storeInvigilator'])->name('store-invigilator');
            Route::delete('invigilators/{invigilator}', [ExamSchedulingController::class, 'deleteInvigilator'])->name('delete-invigilator');
        });
    });

    // Question Bank
    Route::get('questions-import', [QuestionController::class, 'bulkImport'])->name('questions.import');
    Route::post('questions-import', [QuestionController::class, 'processImport'])->name('questions.process-import');
    Route::resource('questions', QuestionController::class);

    // Smart Lesson Plan (AI-assisted, PDF export)
    Route::get('lesson-plan', [\App\Http\Controllers\LessonPlanController::class, 'index'])->name('lesson-plan.index');
    Route::post('lesson-plan', [\App\Http\Controllers\LessonPlanController::class, 'generate'])->name('lesson-plan.generate');
    Route::post('lesson-plan/pdf', [\App\Http\Controllers\LessonPlanController::class, 'pdf'])->name('lesson-plan.pdf');

    // Certificates
    Route::prefix('certificates')->name('certificates.')->group(function () {
        Route::get('/', [CertificateController::class, 'index'])->name('index');
        Route::get('generate', [CertificateController::class, 'generateForm'])->name('generate');
        Route::post('generate', [CertificateController::class, 'generate'])->name('generate.store');
        Route::get('{certificate}/download', [CertificateController::class, 'downloadCertificate'])->name('download');
        Route::get('bulk/{exam}/download', [CertificateController::class, 'downloadBulk'])->name('bulk-download');
        Route::delete('{certificate}', [CertificateController::class, 'revoke'])->name('revoke');

        // Templates
        Route::get('templates', [CertificateController::class, 'templates'])->name('templates');
        Route::get('templates/create', [CertificateController::class, 'createTemplate'])->name('templates.create');
        Route::get('templates/{template}/edit', [CertificateController::class, 'editTemplate'])->name('templates.edit');
        Route::post('templates', [CertificateController::class, 'storeTemplate'])->name('templates.store');
        Route::put('templates/{template}', [CertificateController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('templates/{template}', [CertificateController::class, 'deleteTemplate'])->name('templates.delete');
        Route::post('templates/preview', [CertificateController::class, 'previewTemplate'])->name('templates.preview');
    });

    // Paper Generator
    Route::prefix('papers')->name('papers.')->group(function () {
        Route::get('/', [PaperGeneratorController::class, 'index'])->name('index');
        Route::get('generate', [PaperGeneratorController::class, 'create'])->name('generate');
        Route::post('preview-counts', [PaperGeneratorController::class, 'preview'])->name('preview');
        Route::post('generate', [PaperGeneratorController::class, 'generate'])->name('store');
        Route::get('{paper}', [PaperGeneratorController::class, 'show'])->name('show');
        Route::get('{paper}/paper.pdf', [PaperGeneratorController::class, 'downloadPaper'])->name('download');
        Route::get('{paper}/answer-key.pdf', [PaperGeneratorController::class, 'downloadAnswerKey'])->name('answer-key');
        Route::post('{paper}/regenerate', [PaperGeneratorController::class, 'regenerate'])->name('regenerate');
        Route::delete('{paper}', [PaperGeneratorController::class, 'destroy'])->name('destroy');
    });
});

// Public admit card verification (QR target)
Route::get('verify/admit/{code}', [ExamSchedulingController::class, 'verifyAdmit'])->name('verify-admit');

// Public certificate verification (QR target)
Route::get('verify/certificate/{code}', [CertificateController::class, 'verify'])->name('verify-certificate');

// Public student ID verification (QR target on student ID cards)
Route::get('verify/student/{admission}', [\App\Http\Controllers\PublicController::class, 'verifyStudent'])->name('verify-student');

// Unified Family Portal — serves both student and parent roles from one set
// of routes. The controller adapts the response based on the role: students
// see their own data, parents see a child picker that scopes the same pages
// to a chosen child.
Route::prefix('portal')->name('portal.')->middleware('auth')->group(function () {
    Route::get('dashboard', [FamilyPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('results', [FamilyPortalController::class, 'results'])->name('results');
    Route::get('results/{result}', [FamilyPortalController::class, 'resultDetail'])->name('result-detail');
    Route::get('results/{result}/report-card', [FamilyPortalController::class, 'downloadReportCard'])->name('report-card');
});

// Legacy aliases — old bookmarks / emails to /my/* and /parent/* keep working
// by redirecting to the new unified URLs. Safe to delete after a release or two.
Route::prefix('my')->name('student-portal.')->middleware('auth')->group(function () {
    Route::get('dashboard', fn () => redirect()->route('portal.dashboard'))->name('dashboard');
    Route::get('results', fn () => redirect()->route('portal.results'))->name('results');
    Route::get('results/{result}', fn ($result) => redirect()->route('portal.result-detail', $result))->name('result-detail');
    Route::get('results/{result}/report-card', fn ($result) => redirect()->route('portal.report-card', $result))->name('report-card');
});
Route::prefix('parent')->name('parent-portal.')->middleware('auth')->group(function () {
    Route::get('dashboard', fn () => redirect()->route('portal.dashboard'))->name('dashboard');
    Route::get('children/{student}', fn ($student) => redirect()->route('portal.dashboard', ['student_id' => $student]))->name('child-results');
});

// Bind {transfer} param to StudentTransfer model
Route::model('transfer', StudentTransfer::class);

// Bind scheduling params
Route::model('room', \App\Models\ExamRoom::class);
Route::model('invigilator', \App\Models\ExamInvigilator::class);

// One-shot prod fix: re-sync role permissions + clear caches. Requires
// being logged in as super-admin so it can't be triggered by anyone. Delete
// this route after running it once. Hit /admin/sync-permissions in browser.
Route::middleware(['auth'])->get('admin/sync-permissions', function () {
    abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    \Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    \Artisan::call('permission:cache-reset');
    \Artisan::call('optimize:clear');
    $u = auth()->user();
    return response()->json([
        'ok' => true,
        'seeder_output' => \Artisan::output(),
        'your_user' => [
            'name' => $u->name,
            'roles' => $u->getRoleNames(),
            'permissions' => $u->getAllPermissions()->pluck('name')->sort()->values(),
        ],
    ]);
});

require __DIR__.'/auth.php';
