<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ExamType;
use App\Models\GradingScale;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production-safe baseline seed.
 *
 * Creates ONLY what the system needs to start running:
 *   - One DDO (super-admin) account so the user can log in
 *   - Current academic session
 *   - Master subjects (curriculum-standard, can be edited later)
 *   - Standard exam types
 *   - Punjab Board grading scale
 *
 * Does NOT create: schools, principals, teachers, students, exams, marks,
 * results, questions, or any demo content. The DDO will add their own real
 * data through the UI after first login.
 */
class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        // ─── DDO (Super Admin) ───
        // school_id is NULL — DDO oversees all schools and is not affiliated
        // with any single one. They will create real schools after login.
        $ddo = User::firstOrCreate(
            ['email' => 'ddo@exam.com'],
            [
                'name' => 'DDO Admin',
                'password' => 'password',
                'school_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        if (!$ddo->hasRole('super-admin')) {
            $ddo->assignRole('super-admin');
        }

        // ─── Current Academic Session ───
        // System needs at least one session for everything to be scoped to.
        $year = (int) now()->year;
        $sessionName = $year . '-' . substr((string) ($year + 1), 2);
        AcademicSession::firstOrCreate(
            ['name' => $sessionName],
            [
                'slug' => $sessionName,
                'start_date' => "{$year}-04-01",
                'end_date' => ($year + 1) . '-03-31',
                'is_current' => true,
                'is_active' => true,
            ]
        );

        // ─── Master Subjects (Pakistani curriculum) ───
        $subjects = [
            ['name' => 'English',            'code' => 'ENG',  'type' => 'core',     'is_main' => true,  'sort_order' => 1],
            ['name' => 'Urdu',               'code' => 'URD',  'type' => 'core',     'is_main' => true,  'sort_order' => 2],
            ['name' => 'Islamiyat',          'code' => 'ISL',  'type' => 'core',     'is_main' => true,  'sort_order' => 3],
            ['name' => 'Mathematics',        'code' => 'MATH', 'type' => 'core',     'is_main' => true,  'sort_order' => 4],
            ['name' => 'Science',            'code' => 'SCI',  'type' => 'core',     'is_main' => true,  'sort_order' => 5],
            ['name' => 'Social Studies',     'code' => 'SST',  'type' => 'core',     'is_main' => true,  'sort_order' => 6],
            ['name' => 'Pakistan Studies',   'code' => 'PST',  'type' => 'core',     'is_main' => true,  'sort_order' => 7],
            ['name' => 'Computer Science',   'code' => 'CS',   'type' => 'elective', 'is_main' => false, 'sort_order' => 8],
            ['name' => 'Arabic',             'code' => 'ARB',  'type' => 'elective', 'is_main' => false, 'sort_order' => 9],
            ['name' => 'Physical Education', 'code' => 'PE',   'type' => 'elective', 'is_main' => false, 'sort_order' => 10],
        ];
        foreach ($subjects as $data) {
            Subject::firstOrCreate(['code' => $data['code']], $data);
        }

        // ─── Standard Exam Types ───
        $examTypes = [
            ['name' => 'Monthly Test',        'slug' => 'monthly-test',        'sort_order' => 1],
            ['name' => 'First Term',          'slug' => 'first-term',          'sort_order' => 2],
            ['name' => 'Mid Term',            'slug' => 'mid-term',            'sort_order' => 3],
            ['name' => 'Second Term',         'slug' => 'second-term',         'sort_order' => 4],
            ['name' => 'Annual Examination',  'slug' => 'annual-examination',  'sort_order' => 5],
            ['name' => 'Unit Test',           'slug' => 'unit-test',           'sort_order' => 6],
        ];
        foreach ($examTypes as $type) {
            ExamType::firstOrCreate(['slug' => $type['slug']], $type);
        }

        // ─── Punjab Board Grading Scale ───
        $scale = GradingScale::firstOrCreate(
            ['name' => 'Punjab Board Grading Scale'],
            ['is_default' => true, 'is_active' => true]
        );
        $grades = [
            ['grade' => 'A+', 'label' => 'Outstanding',  'min_percentage' => 90,    'max_percentage' => 100,   'grade_point' => 10, 'remark' => 'Excellent',         'sort_order' => 1],
            ['grade' => 'A',  'label' => 'Excellent',    'min_percentage' => 80,    'max_percentage' => 89.99, 'grade_point' => 9,  'remark' => 'Very Good',         'sort_order' => 2],
            ['grade' => 'B+', 'label' => 'Very Good',    'min_percentage' => 70,    'max_percentage' => 79.99, 'grade_point' => 8,  'remark' => 'Good',              'sort_order' => 3],
            ['grade' => 'B',  'label' => 'Good',         'min_percentage' => 60,    'max_percentage' => 69.99, 'grade_point' => 7,  'remark' => 'Above Average',     'sort_order' => 4],
            ['grade' => 'C',  'label' => 'Satisfactory', 'min_percentage' => 50,    'max_percentage' => 59.99, 'grade_point' => 6,  'remark' => 'Average',           'sort_order' => 5],
            ['grade' => 'D',  'label' => 'Pass',         'min_percentage' => 40,    'max_percentage' => 49.99, 'grade_point' => 5,  'remark' => 'Below Average',     'sort_order' => 6],
            ['grade' => 'E',  'label' => 'Bare Pass',    'min_percentage' => 33,    'max_percentage' => 39.99, 'grade_point' => 4,  'remark' => 'Needs Improvement', 'sort_order' => 7],
            ['grade' => 'F',  'label' => 'Fail',         'min_percentage' => 0,     'max_percentage' => 32.99, 'grade_point' => 0,  'remark' => 'Failed',            'sort_order' => 8],
        ];
        foreach ($grades as $grade) {
            $scale->entries()->firstOrCreate(['grade' => $grade['grade']], $grade);
        }

        $this->command?->info('');
        $this->command?->info('═══════════════════════════════════════════════════');
        $this->command?->info('System ready. Login with:');
        $this->command?->info('  Email:    ddo@exam.com');
        $this->command?->info('  Password: password');
        $this->command?->info('═══════════════════════════════════════════════════');
        $this->command?->info('Add real schools, principals, teachers, students,');
        $this->command?->info('exams, etc. through the UI after first login.');
    }
}
