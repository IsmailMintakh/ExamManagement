<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ExamType;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Services\ResultProcessingService;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    // Pakistani Muslim male first names
    protected array $maleNames = [
        'Muhammad', 'Ahmed', 'Ali', 'Hassan', 'Hussain', 'Usman', 'Bilal', 'Ibrahim',
        'Abdullah', 'Umar', 'Zain', 'Zaid', 'Hamza', 'Salman', 'Faisal', 'Asad',
        'Adnan', 'Tariq', 'Kashif', 'Fahad', 'Waleed', 'Arsalan', 'Haris', 'Danyal',
        'Talha', 'Mustafa', 'Mohsin', 'Imran', 'Irfan', 'Rizwan', 'Khalid', 'Yasir',
        'Saad', 'Shahid', 'Naveed', 'Junaid', 'Sohail', 'Arshad', 'Owais', 'Usama',
        'Yousaf', 'Anas', 'Rehan', 'Umair', 'Farhan', 'Saqib', 'Abdul Rehman', 'Abdul Hadi',
        'Haider', 'Raza', 'Ayan', 'Ahsan', 'Aqib', 'Bilal Ahmed', 'Shayan',
    ];

    // Pakistani Muslim female first names
    protected array $femaleNames = [
        'Ayesha', 'Fatima', 'Maryam', 'Zainab', 'Hafsa', 'Amina', 'Khadija', 'Sana',
        'Saba', 'Sadia', 'Sumaiya', 'Aqsa', 'Anum', 'Bushra', 'Farah', 'Hira',
        'Iqra', 'Kainat', 'Laiba', 'Mahnoor', 'Mehwish', 'Nadia', 'Nazia',
        'Nimra', 'Noor', 'Rabia', 'Rahma', 'Samia', 'Sara', 'Sidra', 'Tehmina',
        'Uzma', 'Wajiha', 'Yasmin', 'Zara', 'Rida', 'Alishba', 'Anaya', 'Areeba',
        'Hareem', 'Javeria', 'Kiran', 'Mariyam', 'Nida', 'Rimsha', 'Sania', 'Tahira',
    ];

    // Pakistani family names
    protected array $familyNames = [
        'Khan', 'Ahmed', 'Raza', 'Hussain', 'Malik', 'Shaikh', 'Shah', 'Qureshi',
        'Siddiqui', 'Ansari', 'Butt', 'Chaudhary', 'Cheema', 'Mughal', 'Baig',
        'Awan', 'Farooqi', 'Rashid', 'Javed', 'Saeed', 'Akhtar', 'Mahmood',
        'Aslam', 'Rafiq', 'Hashmi', 'Abbasi', 'Gilani', 'Rizvi', 'Iqbal', 'Latif',
        'Nawaz', 'Pervez', 'Bhatti', 'Dogar', 'Ranjha', 'Gondal', 'Warraich',
    ];

    // Pakistani cities for addresses
    protected array $cities = [
        'Lahore', 'Gujranwala', 'Sialkot', 'Faisalabad', 'Rawalpindi',
        'Multan', 'Bahawalpur', 'Sargodha', 'Sahiwal', 'Jhelum',
    ];

    protected array $areas = [
        'Model Town', 'Gulberg', 'DHA Phase 4', 'Johar Town', 'Allama Iqbal Town',
        'Shadman', 'Cantt', 'Satellite Town', 'Wapda Town', 'Garden Town',
    ];

    protected array $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

    public function run(): void
    {
        mt_srand(42);

        $this->command->info('Seeding Pakistani demo data...');

        $session = AcademicSession::where('is_current', true)->first();
        if (!$session) {
            $this->command->error('No current academic session. Run DefaultDataSeeder first.');
            return;
        }

        // Previous session for archive
        if (!AcademicSession::where('name', '2024-25')->exists()) {
            AcademicSession::create([
                'name' => '2024-25',
                'slug' => '2024-25',
                'start_date' => '2024-04-01',
                'end_date' => '2025-03-31',
                'is_current' => false,
                'is_active' => true,
            ]);
        }

        $schools = School::active()->get();
        $subjects = Subject::active()->get();

        if ($schools->isEmpty() || $subjects->isEmpty()) {
            $this->command->error('Run DefaultDataSeeder first.');
            return;
        }

        foreach ($schools as $school) {
            $this->command->info("  School: {$school->name}");
            $this->seedSchool($school, $subjects, $session);
        }

        $this->command->info('Creating exams...');
        $this->seedExams($session, $subjects);

        $this->command->info('Flagging supplementary-eligible students...');
        $this->flagSupplementaryEligible();

        $this->command->info('Done. Login with:');
        $this->command->info('  DDO:       ddo@exam.com / password');
        $this->command->info('  Principal: principal@gbhs-lhr.edu.pk / password');
    }

    protected function randomMaleName(): string
    {
        return $this->maleNames[array_rand($this->maleNames)] . ' ' . $this->familyNames[array_rand($this->familyNames)];
    }

    protected function randomFemaleName(): string
    {
        return $this->femaleNames[array_rand($this->femaleNames)] . ' ' . $this->familyNames[array_rand($this->familyNames)];
    }

    protected function randomPhone(): string
    {
        return '+92-3' . mt_rand(0, 4) . mt_rand(0, 9) . '-' . mt_rand(1000000, 9999999);
    }

    protected function randomAddress(): string
    {
        $house = mt_rand(1, 999);
        $street = mt_rand(1, 40);
        return "House {$house}, Street {$street}, " .
            $this->areas[array_rand($this->areas)] . ', ' .
            $this->cities[array_rand($this->cities)];
    }

    protected function seedSchool(School $school, $subjects, $session): void
    {
        // Determine if this is a girls' school by name
        $isGirlsSchool = stripos($school->name, 'Girls') !== false;

        $classConfigs = [
            ['name' => 'Class 6', 'numeric' => 6, 'sections' => ['A', 'B']],
            ['name' => 'Class 7', 'numeric' => 7, 'sections' => ['A']],
            ['name' => 'Class 8', 'numeric' => 8, 'sections' => ['A', 'B']],
        ];

        foreach ($classConfigs as $idx => $config) {
            $class = SchoolClass::firstOrCreate(
                ['school_id' => $school->id, 'name' => $config['name']],
                [
                    'numeric_name' => $config['numeric'],
                    'sort_order' => $idx + 1,
                    'is_active' => true,
                ]
            );

            // Attach core subjects
            $coreSubjects = $subjects->where('type', 'core');
            $class->subjects()->syncWithoutDetaching($coreSubjects->pluck('id'));

            // Add CS for higher classes
            if ($config['numeric'] >= 7) {
                $cs = $subjects->firstWhere('code', 'CS');
                if ($cs) $class->subjects()->syncWithoutDetaching([$cs->id]);
            }

            foreach ($config['sections'] as $sectionName) {
                $ctName = $isGirlsSchool ? $this->randomFemaleName() : $this->randomMaleName();
                $classTeacher = $this->createOrGetTeacher(
                    $school,
                    "{$school->code}-CT-{$config['name']}-{$sectionName}",
                    $ctName,
                    'class-teacher'
                );

                $section = Section::firstOrCreate(
                    ['school_class_id' => $class->id, 'name' => $sectionName],
                    [
                        'class_teacher_id' => $classTeacher->id,
                        'capacity' => 40,
                        'is_active' => true,
                    ]
                );

                $studentCount = mt_rand(10, 14);
                for ($i = 1; $i <= $studentCount; $i++) {
                    $this->createStudent($school, $class, $section, $session, $i, $isGirlsSchool);
                }
            }

            foreach ($coreSubjects as $subject) {
                $stName = $isGirlsSchool ? $this->randomFemaleName() : $this->randomMaleName();
                $subjectTeacher = $this->createOrGetTeacher(
                    $school,
                    "{$school->code}-ST-{$subject->code}-{$config['numeric']}",
                    $stName,
                    'subject-teacher'
                );

                foreach ($class->sections as $section) {
                    SubjectTeacher::firstOrCreate([
                        'user_id' => $subjectTeacher->id,
                        'subject_id' => $subject->id,
                        'school_class_id' => $class->id,
                        'section_id' => $section->id,
                        'academic_session_id' => $session->id,
                    ], ['is_active' => true]);
                }
            }
        }
    }

    protected function createOrGetTeacher(School $school, string $emailPrefix, string $name, string $role): User
    {
        $email = strtolower(str_replace(' ', '.', $emailPrefix)) . '@school.edu.pk';
        $email = preg_replace('/[^a-z0-9@.\-]/', '', $email);

        $user = User::where('email', $email)->first();
        if ($user) return $user;

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'school_id' => $school->id,
            'phone' => $this->randomPhone(),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function createStudent(School $school, SchoolClass $class, Section $section, AcademicSession $session, int $rollNo, bool $isGirlsSchool): ?Student
    {
        $admissionNo = $school->code . '-' . $class->numeric_name . $section->name . '-' . str_pad((string) $rollNo, 3, '0', STR_PAD_LEFT);
        if (Student::where('admission_no', $admissionNo)->exists()) return null;

        $gender = $isGirlsSchool ? 'female' : 'male';
        $name = $gender === 'male' ? $this->randomMaleName() : $this->randomFemaleName();
        $fatherName = $this->randomMaleName();
        $motherName = $this->randomFemaleName();

        // Parent account for every 3rd student
        $parentUserId = null;
        if ($rollNo % 3 === 0) {
            $parentEmail = "parent.{$admissionNo}@demo.pk";
            $parentEmail = strtolower(preg_replace('/[^a-z0-9@.\-]/', '', $parentEmail));
            $parentUser = User::firstOrCreate(
                ['email' => $parentEmail],
                [
                    'name' => $fatherName,
                    'password' => 'password',
                    'school_id' => $school->id,
                    'phone' => $this->randomPhone(),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            if ($parentUser->wasRecentlyCreated || !$parentUser->hasRole('parent')) {
                $parentUser->assignRole('parent');
            }
            $parentUserId = $parentUser->id;
        }

        // Student login for first student in each section
        $studentUserId = null;
        if ($rollNo === 1) {
            $studentEmail = "student.{$admissionNo}@demo.pk";
            $studentEmail = strtolower(preg_replace('/[^a-z0-9@.\-]/', '', $studentEmail));
            $studentUser = User::firstOrCreate(
                ['email' => $studentEmail],
                [
                    'name' => $name,
                    'password' => 'password',
                    'school_id' => $school->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            if ($studentUser->wasRecentlyCreated || !$studentUser->hasRole('student')) {
                $studentUser->assignRole('student');
            }
            $studentUserId = $studentUser->id;
        }

        $dobYear = 2026 - (5 + $class->numeric_name) - mt_rand(0, 1);
        $dob = sprintf('%04d-%02d-%02d', $dobYear, mt_rand(1, 12), mt_rand(1, 28));

        return Student::create([
            'user_id' => $studentUserId,
            'parent_user_id' => $parentUserId,
            'admission_no' => $admissionNo,
            'roll_no' => $rollNo,
            'name' => $name,
            'father_name' => $fatherName,
            'mother_name' => $motherName,
            'guardian_phone' => $this->randomPhone(),
            'date_of_birth' => $dob,
            'gender' => $gender,
            'address' => $this->randomAddress(),
            'blood_group' => $this->bloodGroups[array_rand($this->bloodGroups)],
            'school_id' => $school->id,
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);
    }

    protected function seedExams(AcademicSession $session, $subjects): void
    {
        $resultService = app(ResultProcessingService::class);
        $gradingScale = GradingScale::where('is_default', true)->first() ?? GradingScale::first();
        $ddo = User::role('super-admin')->first();

        if (!$ddo) return;

        // Exam 1: First Term (completed)
        $firstTermType = ExamType::where('slug', 'first-term')->first();
        $firstTerm = Exam::firstOrCreate(
            ['name' => 'First Term Examination 2025-26', 'academic_session_id' => $session->id],
            [
                'slug' => 'first-term-2025-26',
                'exam_type_id' => $firstTermType?->id,
                'grading_scale_id' => $gradingScale?->id,
                'start_date' => now()->subMonths(2)->format('Y-m-d'),
                'end_date' => now()->subMonths(2)->addDays(7)->format('Y-m-d'),
                'description' => 'First term examination for academic year 2025-26',
                'total_marks' => 100,
                'passing_marks' => 33,
                'passing_percentage' => 33,
                'main_subjects_must_pass' => true,
                'all_subjects_must_pass' => false,
                'grace_marks' => 5,
                'grace_marks_max_subjects' => 2,
                'position_calculation' => 'section',
                'apply_to_all_schools' => true,
                'status' => 'completed',
                'created_by' => $ddo->id,
            ]
        );
        $firstTerm->schools()->sync(School::active()->pluck('id'));
        $this->createExamSubjects($firstTerm, $subjects);
        $this->command->info('  First Term: generating marks & results...');
        $this->generateMarksAndResults($firstTerm, $resultService, true);

        // Exam 2: Mid Term (in marks_entry)
        $midTermType = ExamType::where('slug', 'mid-term')->first();
        $midTerm = Exam::firstOrCreate(
            ['name' => 'Mid Term Examination 2025-26', 'academic_session_id' => $session->id],
            [
                'slug' => 'mid-term-2025-26',
                'exam_type_id' => $midTermType?->id,
                'grading_scale_id' => $gradingScale?->id,
                'start_date' => now()->subDays(15)->format('Y-m-d'),
                'end_date' => now()->subDays(8)->format('Y-m-d'),
                'description' => 'Mid term examination for academic year 2025-26',
                'total_marks' => 50,
                'passing_marks' => 17,
                'passing_percentage' => 33,
                'grace_marks' => 0,
                'grace_marks_max_subjects' => 0,
                'position_calculation' => 'section',
                'apply_to_all_schools' => true,
                'status' => 'marks_entry',
                'marks_entry_deadline' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'created_by' => $ddo->id,
            ]
        );
        $midTerm->schools()->sync(School::active()->pluck('id'));
        $this->createExamSubjects($midTerm, $subjects, 50);
        $this->command->info('  Mid Term: partial marks...');
        $this->generatePartialMarks($midTerm);

        // Exam 3: Annual (draft)
        $annualType = ExamType::where('slug', 'annual-examination')->first();
        Exam::firstOrCreate(
            ['name' => 'Annual Examination 2025-26', 'academic_session_id' => $session->id],
            [
                'slug' => 'annual-examination-2025-26',
                'exam_type_id' => $annualType?->id,
                'grading_scale_id' => $gradingScale?->id,
                'start_date' => now()->addDays(30)->format('Y-m-d'),
                'end_date' => now()->addDays(40)->format('Y-m-d'),
                'description' => 'Annual examination for academic year 2025-26',
                'total_marks' => 100,
                'passing_marks' => 33,
                'passing_percentage' => 33,
                'main_subjects_must_pass' => true,
                'grace_marks' => 5,
                'grace_marks_max_subjects' => 2,
                'position_calculation' => 'section',
                'apply_to_all_schools' => true,
                'status' => 'draft',
                'created_by' => $ddo->id,
            ]
        )->schools()->sync(School::active()->pluck('id'));
    }

    protected function createExamSubjects(Exam $exam, $subjects, $totalMarks = 100): void
    {
        $passingMarks = (int) ($totalMarks * 0.33);
        $classes = SchoolClass::active()->with('subjects')->get();

        foreach ($classes as $class) {
            foreach ($class->subjects as $subject) {
                ExamSubject::firstOrCreate([
                    'exam_id' => $exam->id,
                    'subject_id' => $subject->id,
                    'school_class_id' => $class->id,
                ], [
                    'total_marks' => $totalMarks,
                    'passing_marks' => $passingMarks,
                    'exam_date' => $exam->start_date,
                ]);
            }
        }
    }

    protected function generateMarksAndResults(Exam $exam, ResultProcessingService $service, bool $generateResults = true): void
    {
        $exam->load('examSubjects');
        $sections = Section::active()->with(['schoolClass', 'students' => fn ($q) => $q->where('academic_session_id', $exam->academic_session_id)])->get();

        foreach ($sections as $section) {
            if ($section->students->isEmpty()) continue;

            $examSubjects = $exam->examSubjects->where('school_class_id', $section->school_class_id);
            if ($examSubjects->isEmpty()) continue;

            foreach ($examSubjects as $examSubject) {
                $subjectTeacher = SubjectTeacher::where('subject_id', $examSubject->subject_id)
                    ->where('section_id', $section->id)
                    ->first()?->user;
                $teacherId = $subjectTeacher?->id ?? User::role('super-admin')->first()?->id;

                foreach ($section->students as $student) {
                    $marks = $this->generateRealisticMark($examSubject->total_marks);
                    $isAbsent = mt_rand(1, 100) <= 2;

                    Mark::updateOrCreate([
                        'exam_id' => $exam->id,
                        'subject_id' => $examSubject->subject_id,
                        'student_id' => $student->id,
                        'section_id' => $section->id,
                    ], [
                        'exam_subject_id' => $examSubject->id,
                        'school_id' => $section->schoolClass->school_id,
                        'school_class_id' => $section->school_class_id,
                        'academic_session_id' => $exam->academic_session_id,
                        'marks_obtained' => $isAbsent ? 0 : $marks,
                        'total_marks' => $examSubject->total_marks,
                        'grace_marks' => 0,
                        'is_absent' => $isAbsent,
                        'status' => 'submitted',
                        'entered_by' => $teacherId,
                        'submitted_at' => now()->subDays(5),
                    ]);
                }

                MarksSubmission::updateOrCreate([
                    'exam_id' => $exam->id,
                    'subject_id' => $examSubject->subject_id,
                    'section_id' => $section->id,
                ], [
                    'school_class_id' => $section->school_class_id,
                    'school_id' => $section->schoolClass->school_id,
                    'submitted_by' => $teacherId,
                    'status' => 'submitted',
                    'submitted_at' => now()->subDays(5),
                ]);
            }

            if ($generateResults) {
                try {
                    $service->generateResults($exam, $section->school_class_id, $section->id);
                } catch (\Throwable $e) {
                    $this->command->warn("    Result generation failed for {$section->schoolClass->name} {$section->name}: {$e->getMessage()}");
                }
            }
        }
    }

    protected function generatePartialMarks(Exam $exam): void
    {
        $exam->load('examSubjects');
        $sections = Section::active()->with(['schoolClass', 'students' => fn ($q) => $q->where('academic_session_id', $exam->academic_session_id)])->get();

        foreach ($sections as $section) {
            if ($section->students->isEmpty()) continue;
            $examSubjects = $exam->examSubjects->where('school_class_id', $section->school_class_id);
            if ($examSubjects->isEmpty()) continue;

            foreach ($examSubjects->take(2) as $examSubject) {
                $subjectTeacher = SubjectTeacher::where('subject_id', $examSubject->subject_id)
                    ->where('section_id', $section->id)
                    ->first()?->user;
                $teacherId = $subjectTeacher?->id ?? User::role('super-admin')->first()?->id;

                foreach ($section->students as $student) {
                    Mark::updateOrCreate([
                        'exam_id' => $exam->id,
                        'subject_id' => $examSubject->subject_id,
                        'student_id' => $student->id,
                        'section_id' => $section->id,
                    ], [
                        'exam_subject_id' => $examSubject->id,
                        'school_id' => $section->schoolClass->school_id,
                        'school_class_id' => $section->school_class_id,
                        'academic_session_id' => $exam->academic_session_id,
                        'marks_obtained' => $this->generateRealisticMark($examSubject->total_marks),
                        'total_marks' => $examSubject->total_marks,
                        'grace_marks' => 0,
                        'is_absent' => false,
                        'status' => 'submitted',
                        'entered_by' => $teacherId,
                        'submitted_at' => now()->subDays(2),
                    ]);
                }

                MarksSubmission::updateOrCreate([
                    'exam_id' => $exam->id,
                    'subject_id' => $examSubject->subject_id,
                    'section_id' => $section->id,
                ], [
                    'school_class_id' => $section->school_class_id,
                    'school_id' => $section->schoolClass->school_id,
                    'submitted_by' => $teacherId,
                    'status' => 'submitted',
                    'submitted_at' => now()->subDays(2),
                ]);
            }
        }
    }

    protected function generateRealisticMark(int $total): int
    {
        $rand = mt_rand(1, 100);

        if ($rand <= 5) {
            return mt_rand(10, (int) ($total * 0.32));
        } elseif ($rand <= 25) {
            return mt_rand((int) ($total * 0.33), (int) ($total * 0.5));
        } elseif ($rand <= 65) {
            return mt_rand((int) ($total * 0.5), (int) ($total * 0.7));
        } elseif ($rand <= 90) {
            return mt_rand((int) ($total * 0.7), (int) ($total * 0.85));
        } else {
            return mt_rand((int) ($total * 0.85), $total);
        }
    }

    /**
     * Auto-flag students who failed 1–2 subjects as eligible for supplementary,
     * then simulate a few supplementary attempts (some passed, some pending).
     */
    protected function flagSupplementaryEligible(): void
    {
        $threshold = 2;
        $eligibleCount = 0;
        $simulatedPass = 0;

        $failedResults = \App\Models\Result::where('is_passed', false)->get();

        foreach ($failedResults as $result) {
            $subjectResults = $result->subject_results ?? [];

            $failedSubjectIds = collect($subjectResults)
                ->filter(fn ($sr) => !($sr['is_passed'] ?? true) && !($sr['is_absent'] ?? false))
                ->pluck('subject_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $failedCount = count($failedSubjectIds);
            if ($failedCount === 0 || $failedCount > $threshold) {
                continue;
            }

            $result->is_supplementary_eligible = true;
            $result->supplementary_subjects = $failedSubjectIds;
            $result->supplementary_status = 'eligible';

            // Simulate: 30% have appeared and passed, 15% appeared and failed, rest pending
            $roll = mt_rand(1, 100);
            if ($roll <= 30) {
                $result->supplementary_status = 'passed';
                $simulatedPass++;
            } elseif ($roll <= 45) {
                $result->supplementary_status = 'failed';
            }

            $result->save();
            $eligibleCount++;
        }

        $this->command->info("  {$eligibleCount} students flagged supplementary-eligible ({$simulatedPass} already passed the re-exam).");
    }
}
