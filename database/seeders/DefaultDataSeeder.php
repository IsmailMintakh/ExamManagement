<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ExamType;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        // --------- Schools (Punjab, Pakistan) ---------
        $mainSchool = School::create([
            'name' => 'Government Boys High School, Lahore',
            'code' => 'GBHS-LHR',
            'address' => 'Mall Road, Lahore, Punjab',
            'phone' => '+92-42-99201234',
            'email' => 'principal@gbhs-lhr.edu.pk',
            'is_main' => true,
            'is_active' => true,
        ]);

        $school2 = School::create([
            'name' => 'Government Girls High School, Gujranwala',
            'code' => 'GGHS-GRW',
            'address' => 'G.T. Road, Gujranwala, Punjab',
            'phone' => '+92-55-3720456',
            'email' => 'principal@gghs-grw.edu.pk',
            'is_main' => false,
            'is_active' => true,
        ]);

        $school3 = School::create([
            'name' => 'Government Pilot Secondary School, Sialkot',
            'code' => 'GPSS-SKT',
            'address' => 'Paris Road, Sialkot, Punjab',
            'phone' => '+92-52-4258899',
            'email' => 'principal@gpss-skt.edu.pk',
            'is_main' => false,
            'is_active' => true,
        ]);

        // --------- DDO (Super Admin) ---------
        $superAdmin = User::create([
            'name' => 'Muhammad Tariq Mahmood',
            'email' => 'ddo@exam.com',
            'password' => 'password',
            'school_id' => $mainSchool->id,
            'phone' => '+92-300-1234567',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super-admin');

        // --------- Principals ---------
        $principal1 = User::create([
            'name' => 'Ahmed Hassan Qureshi',
            'email' => 'principal@gbhs-lhr.edu.pk',
            'password' => 'password',
            'school_id' => $mainSchool->id,
            'phone' => '+92-321-4567890',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $principal1->assignRole('school-admin');

        $principal2 = User::create([
            'name' => 'Fatima Zahra Siddiqui',
            'email' => 'principal@gghs-grw.edu.pk',
            'password' => 'password',
            'school_id' => $school2->id,
            'phone' => '+92-333-5678901',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $principal2->assignRole('school-admin');

        $principal3 = User::create([
            'name' => 'Imran Khalid Butt',
            'email' => 'principal@gpss-skt.edu.pk',
            'password' => 'password',
            'school_id' => $school3->id,
            'phone' => '+92-345-6789012',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $principal3->assignRole('school-admin');

        // --------- Academic Session ---------
        AcademicSession::create([
            'name' => '2025-26',
            'slug' => '2025-26',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        // --------- Subjects (Pakistani curriculum) ---------
        $subjects = [
            ['name' => 'English', 'code' => 'ENG', 'type' => 'core', 'is_main' => true, 'sort_order' => 1],
            ['name' => 'Urdu', 'code' => 'URD', 'type' => 'core', 'is_main' => true, 'sort_order' => 2],
            ['name' => 'Islamiyat', 'code' => 'ISL', 'type' => 'core', 'is_main' => true, 'sort_order' => 3],
            ['name' => 'Mathematics', 'code' => 'MATH', 'type' => 'core', 'is_main' => true, 'sort_order' => 4],
            ['name' => 'Science', 'code' => 'SCI', 'type' => 'core', 'is_main' => true, 'sort_order' => 5],
            ['name' => 'Social Studies', 'code' => 'SST', 'type' => 'core', 'is_main' => true, 'sort_order' => 6],
            ['name' => 'Pakistan Studies', 'code' => 'PST', 'type' => 'core', 'is_main' => true, 'sort_order' => 7],
            ['name' => 'Computer Science', 'code' => 'CS', 'type' => 'elective', 'is_main' => false, 'sort_order' => 8],
            ['name' => 'Arabic', 'code' => 'ARB', 'type' => 'elective', 'is_main' => false, 'sort_order' => 9],
            ['name' => 'Physical Education', 'code' => 'PE', 'type' => 'elective', 'is_main' => false, 'sort_order' => 10],
        ];

        foreach ($subjects as $subject) {
            Subject::create($subject);
        }

        // --------- Exam Types ---------
        $examTypes = [
            ['name' => 'Monthly Test', 'slug' => 'monthly-test', 'sort_order' => 1],
            ['name' => 'First Term', 'slug' => 'first-term', 'sort_order' => 2],
            ['name' => 'Mid Term', 'slug' => 'mid-term', 'sort_order' => 3],
            ['name' => 'Second Term', 'slug' => 'second-term', 'sort_order' => 4],
            ['name' => 'Annual Examination', 'slug' => 'annual-examination', 'sort_order' => 5],
            ['name' => 'Unit Test', 'slug' => 'unit-test', 'sort_order' => 6],
        ];

        foreach ($examTypes as $type) {
            ExamType::create($type);
        }

        // --------- Grading Scale (Punjab Board style) ---------
        $scale = GradingScale::create([
            'name' => 'Punjab Board Grading Scale',
            'is_default' => true,
            'is_active' => true,
        ]);

        $grades = [
            ['grade' => 'A+', 'label' => 'Outstanding',   'min_percentage' => 90, 'max_percentage' => 100,   'grade_point' => 10, 'remark' => 'Excellent',         'sort_order' => 1],
            ['grade' => 'A',  'label' => 'Excellent',     'min_percentage' => 80, 'max_percentage' => 89.99, 'grade_point' => 9,  'remark' => 'Very Good',         'sort_order' => 2],
            ['grade' => 'B+', 'label' => 'Very Good',     'min_percentage' => 70, 'max_percentage' => 79.99, 'grade_point' => 8,  'remark' => 'Good',              'sort_order' => 3],
            ['grade' => 'B',  'label' => 'Good',          'min_percentage' => 60, 'max_percentage' => 69.99, 'grade_point' => 7,  'remark' => 'Above Average',     'sort_order' => 4],
            ['grade' => 'C',  'label' => 'Satisfactory',  'min_percentage' => 50, 'max_percentage' => 59.99, 'grade_point' => 6,  'remark' => 'Average',           'sort_order' => 5],
            ['grade' => 'D',  'label' => 'Pass',          'min_percentage' => 40, 'max_percentage' => 49.99, 'grade_point' => 5,  'remark' => 'Below Average',     'sort_order' => 6],
            ['grade' => 'E',  'label' => 'Bare Pass',     'min_percentage' => 33, 'max_percentage' => 39.99, 'grade_point' => 4,  'remark' => 'Needs Improvement', 'sort_order' => 7],
            ['grade' => 'F',  'label' => 'Fail',          'min_percentage' => 0,  'max_percentage' => 32.99, 'grade_point' => 0,  'remark' => 'Failed',            'sort_order' => 8],
        ];

        foreach ($grades as $grade) {
            $scale->entries()->create($grade);
        }
    }
}
