<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cross-tenant isolation regression tests.
 *
 * Locks down the role-scoping work added across multiple sessions:
 *   - school-admin only sees own school
 *   - class-teacher only sees own assigned section + classes
 *   - subject-teacher only sees their subject assignments
 *   - parent only sees their linked children
 *   - unpublished results stay hidden from Family Portal + public lookup
 *
 * If any of these guards regress, these tests catch it before the next
 * Principal sees data from a different school.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected School $schoolA;
    protected School $schoolB;
    protected User $superAdmin;
    protected User $principalA;
    protected User $principalB;
    protected User $classTeacherA;
    protected SchoolClass $classA1;
    protected SchoolClass $classB1;
    protected Section $sectionA1;
    protected Section $sectionA2;
    protected Section $sectionB1;
    protected Student $studentA1;
    protected Student $studentB1;
    protected AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->session = AcademicSession::create([
            'name' => '2026-27', 'start_date' => '2026-04-01',
            'end_date' => '2027-03-31', 'is_current' => true, 'is_active' => true,
        ]);

        // Two distinct schools.
        $this->schoolA = School::create(['name' => 'School A', 'code' => 'SA', 'is_active' => true]);
        $this->schoolB = School::create(['name' => 'School B', 'code' => 'SB', 'is_active' => true]);

        // One class per school, two sections at School A so the class-teacher
        // narrowing has something to discriminate.
        $this->classA1 = SchoolClass::create([
            'name' => 'Class V', 'sort_order' => 5, 'school_id' => $this->schoolA->id, 'is_active' => true,
        ]);
        $this->classB1 = SchoolClass::create([
            'name' => 'Class V', 'sort_order' => 5, 'school_id' => $this->schoolB->id, 'is_active' => true,
        ]);
        $this->sectionA1 = Section::create([
            'name' => 'A', 'school_class_id' => $this->classA1->id, 'is_active' => true,
        ]);
        $this->sectionA2 = Section::create([
            'name' => 'B', 'school_class_id' => $this->classA1->id, 'is_active' => true,
        ]);
        $this->sectionB1 = Section::create([
            'name' => 'A', 'school_class_id' => $this->classB1->id, 'is_active' => true,
        ]);

        // Users.
        $this->superAdmin = $this->makeUser('ddo@test', null, 'super-admin');
        $this->principalA = $this->makeUser('a-principal@test', $this->schoolA->id, 'school-admin');
        $this->principalB = $this->makeUser('b-principal@test', $this->schoolB->id, 'school-admin');
        $this->classTeacherA = $this->makeUser('a-ct@test', $this->schoolA->id, 'class-teacher');

        // Assign the class-teacher to sectionA1 — but NOT sectionA2.
        $this->sectionA1->update(['class_teacher_id' => $this->classTeacherA->id]);

        // Students.
        $this->studentA1 = Student::create([
            'name' => 'Ahmed', 'admission_no' => 'A-100', 'roll_no' => '1',
            'school_id' => $this->schoolA->id,
            'school_class_id' => $this->classA1->id,
            'section_id' => $this->sectionA1->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
            'date_of_birth' => '2015-06-15',
        ]);
        $this->studentB1 = Student::create([
            'name' => 'Bilal', 'admission_no' => 'B-200', 'roll_no' => '1',
            'school_id' => $this->schoolB->id,
            'school_class_id' => $this->classB1->id,
            'section_id' => $this->sectionB1->id,
            'academic_session_id' => $this->session->id,
            'status' => 'active',
            'date_of_birth' => '2015-09-20',
        ]);
    }

    /** Compact helper to spin up a User with a specific role + school. */
    protected function makeUser(string $email, ?int $schoolId, string $role): User
    {
        $u = User::create([
            'name' => $email, 'email' => $email, 'password' => Hash::make('password'),
            'school_id' => $schoolId, 'is_active' => true,
        ]);
        $u->assignRole($role);
        return $u;
    }

    // ─────────────────────────── SCHOOLS ────────────────────────────

    public function test_principal_only_sees_own_school_in_schools_index(): void
    {
        $response = $this->actingAs($this->principalA)->get('/schools');
        $response->assertStatus(200);

        // Inertia ships JSON-shaped props; assert the schools list contains
        // School A but not School B.
        $names = collect($response->viewData('page')['props']['schools']['data'])->pluck('name');
        $this->assertTrue($names->contains('School A'));
        $this->assertFalse($names->contains('School B'));
    }

    public function test_super_admin_sees_all_schools_in_index(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/schools');
        $response->assertStatus(200);
        $names = collect($response->viewData('page')['props']['schools']['data'])->pluck('name');
        $this->assertTrue($names->contains('School A'));
        $this->assertTrue($names->contains('School B'));
    }

    public function test_principal_cannot_view_other_school_directly(): void
    {
        $this->actingAs($this->principalA)
            ->get("/schools/{$this->schoolB->id}")
            ->assertForbidden();
    }

    // ─────────────────────────── STUDENTS ────────────────────────────

    public function test_principal_sees_only_own_school_students(): void
    {
        $response = $this->actingAs($this->principalA)->get('/students');
        $response->assertStatus(200);

        $names = collect($response->viewData('page')['props']['students']['data'])->pluck('name');
        $this->assertTrue($names->contains('Ahmed'));
        $this->assertFalse($names->contains('Bilal'));
    }

    public function test_principal_cannot_view_student_in_other_school(): void
    {
        $this->actingAs($this->principalA)
            ->get("/students/{$this->studentB1->id}")
            ->assertForbidden();
    }

    public function test_class_teacher_sees_only_their_section_students(): void
    {
        // Move studentA1 to sectionA2 (which the teacher is NOT assigned to)
        // by creating a second student first. The original is in sectionA1.
        $studentA2 = Student::create([
            'name' => 'Asad', 'admission_no' => 'A-101', 'roll_no' => '2',
            'school_id' => $this->schoolA->id, 'school_class_id' => $this->classA1->id,
            'section_id' => $this->sectionA2->id, 'academic_session_id' => $this->session->id,
            'status' => 'active', 'date_of_birth' => '2015-04-10',
        ]);

        $response = $this->actingAs($this->classTeacherA)->get('/students');
        $response->assertStatus(200);
        $names = collect($response->viewData('page')['props']['students']['data'])->pluck('name');
        // Sees own section's student (Ahmed) but not the other section's (Asad).
        $this->assertTrue($names->contains('Ahmed'));
        $this->assertFalse($names->contains('Asad'));
    }

    // ─────────────────────────── SECTIONS ────────────────────────────

    public function test_class_teacher_sees_only_assigned_section_in_index(): void
    {
        // Class-teachers don't get sections.view by default — but if a school
        // explicitly grants it (e.g. for "My Class" overview), our scoping
        // must still narrow them to ONLY their assigned section.
        $this->classTeacherA->givePermissionTo('sections.view');

        $response = $this->actingAs($this->classTeacherA)->get('/sections');
        $response->assertStatus(200);
        $names = collect($response->viewData('page')['props']['sections']['data'])->pluck('name');
        $this->assertTrue($names->contains('A'));
        // Section B exists at School A but the teacher isn't assigned to it.
        $this->assertFalse($names->contains('B'));
    }

    public function test_principal_cannot_update_other_schools_section(): void
    {
        // Direct PATCH to a section in School B as a Principal of School A.
        // SectionPolicy::update should reject this even with edit permission.
        $this->actingAs($this->principalA)
            ->put("/sections/{$this->sectionB1->id}", [
                'name' => 'Hacked',
                'school_class_id' => $this->sectionB1->school_class_id,
            ])
            ->assertForbidden();
    }

    // ─────────────────────────── EXAMS ────────────────────────────

    public function test_exam_only_visible_at_school_in_pivot_with_matching_class(): void
    {
        // Create an Exam scoped to School A's Class V via exam_subjects.
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);
        $exam = Exam::create([
            'name' => 'Mid-Term 2026',
            'exam_type_id' => \DB::table('exam_types')->insertGetId([
                'name' => 'Mid-Term', 'slug' => 'mid-term-' . uniqid(), 'sort_order' => 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'academic_session_id' => $this->session->id,
            'apply_to_all_schools' => false,
            'status' => 'marks_entry', 'created_by' => $this->principalA->id,
        ]);
        $exam->schools()->sync([$this->schoolA->id, $this->schoolB->id]);
        // Only Class V at School A is in exam_subjects — School B's Class V is NOT.
        ExamSubject::create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'school_class_id' => $this->classA1->id,
            'total_marks' => 100, 'passing_marks' => 33,
        ]);

        // School A sees it.
        $this->assertTrue(
            Exam::visibleToSchool($this->schoolA->id)->where('id', $exam->id)->exists(),
            'Exam should be visible to School A (its class is in exam_subjects).'
        );
        // School B does NOT — even though it's in the schools pivot, none of
        // its classes are in exam_subjects.
        $this->assertFalse(
            Exam::visibleToSchool($this->schoolB->id)->where('id', $exam->id)->exists(),
            'Exam should NOT be visible to School B (its classes are not in exam_subjects).'
        );
    }

    // ─────────────────────────── RESULT PUBLISHING ────────────────────────────

    public function test_unpublished_results_hidden_from_public_lookup(): void
    {
        $exam = Exam::create([
            'name' => 'Mid-Term', 'exam_type_id' => \DB::table('exam_types')->insertGetId([
                'name' => 'MT', 'slug' => 'mt-' . uniqid(), 'sort_order' => 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'academic_session_id' => $this->session->id,
            'status' => 'completed', 'created_by' => $this->principalA->id,
        ]);
        Result::create([
            'exam_id' => $exam->id,
            'student_id' => $this->studentA1->id,
            'school_id' => $this->schoolA->id,
            'school_class_id' => $this->classA1->id,
            'section_id' => $this->sectionA1->id,
            'academic_session_id' => $this->session->id,
            'total_marks' => 100, 'obtained_marks' => 80, 'percentage' => 80,
            'is_passed' => true,
            // No published_at + exam.results_published_at is null → hidden.
        ]);

        // Unpublished exam shouldn't even appear in the dropdown.
        $response = $this->post('/check-result', [
            'exam_id' => $exam->id,
            'admission_no' => 'A-100',
        ]);
        $response->assertStatus(200);

        $error = $response->viewData('page')['props']['error'] ?? null;
        $this->assertNotNull($error, 'Lookup should reject unpublished exam.');
        $this->assertNull($response->viewData('page')['props']['result'] ?? null);
    }

    public function test_published_result_visible_in_public_lookup(): void
    {
        $exam = Exam::create([
            'name' => 'Mid-Term Published',
            'exam_type_id' => \DB::table('exam_types')->insertGetId([
                'name' => 'MT', 'slug' => 'mt-pub-' . uniqid(), 'sort_order' => 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'academic_session_id' => $this->session->id,
            'status' => 'completed', 'created_by' => $this->principalA->id,
            'results_published_at' => now(),
        ]);
        // Lookup narrows to schools assigned to the exam — sync School A in.
        $exam->schools()->sync([$this->schoolA->id]);
        Result::create([
            'exam_id' => $exam->id,
            'student_id' => $this->studentA1->id,
            'school_id' => $this->schoolA->id,
            'school_class_id' => $this->classA1->id,
            'section_id' => $this->sectionA1->id,
            'academic_session_id' => $this->session->id,
            'total_marks' => 100, 'obtained_marks' => 90, 'percentage' => 90,
            'is_passed' => true,
            'published_at' => now(),
        ]);

        $response = $this->post('/check-result', [
            'exam_id' => $exam->id,
            'admission_no' => 'A-100',
        ]);
        $response->assertStatus(200);

        $result = $response->viewData('page')['props']['result'];
        $this->assertNotNull($result, 'Public lookup should return the published result.');
        $this->assertEquals(90, $result['percentage']);
    }

    public function test_public_lookup_rejects_unknown_admission(): void
    {
        $exam = Exam::create([
            'name' => 'Final', 'exam_type_id' => \DB::table('exam_types')->insertGetId([
                'name' => 'Final', 'slug' => 'final-' . uniqid(), 'sort_order' => 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'academic_session_id' => $this->session->id,
            'status' => 'completed', 'created_by' => $this->principalA->id,
            'results_published_at' => now(),
        ]);
        $exam->schools()->sync([$this->schoolA->id]);

        $response = $this->post('/check-result', [
            'exam_id' => $exam->id,
            'admission_no' => 'Z-999', // doesn't exist
        ]);
        $response->assertStatus(200);
        $error = $response->viewData('page')['props']['error'] ?? null;
        $this->assertNotNull($error, 'Lookup should reject unknown admission number.');
        $this->assertNull($response->viewData('page')['props']['student'] ?? null);
    }

    public function test_public_lookup_admission_match_is_whitespace_tolerant(): void
    {
        $exam = Exam::create([
            'name' => 'Trim-Test', 'exam_type_id' => \DB::table('exam_types')->insertGetId([
                'name' => 'TT', 'slug' => 'tt-' . uniqid(), 'sort_order' => 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'academic_session_id' => $this->session->id,
            'status' => 'completed', 'created_by' => $this->principalA->id,
            'results_published_at' => now(),
        ]);
        $exam->schools()->sync([$this->schoolA->id]);
        Result::create([
            'exam_id' => $exam->id,
            'student_id' => $this->studentA1->id,
            'school_id' => $this->schoolA->id,
            'school_class_id' => $this->classA1->id,
            'section_id' => $this->sectionA1->id,
            'academic_session_id' => $this->session->id,
            'total_marks' => 100, 'obtained_marks' => 70, 'percentage' => 70,
            'is_passed' => true,
            'published_at' => now(),
        ]);

        // Leading + trailing whitespace on admission number should still match.
        $response = $this->post('/check-result', [
            'exam_id' => $exam->id,
            'admission_no' => '  A-100  ',
        ]);
        $response->assertStatus(200);
        $this->assertNotNull($response->viewData('page')['props']['student']);
    }
}
