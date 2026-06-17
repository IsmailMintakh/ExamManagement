<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Primary-section Assessment marks. One row per student per academic
 * session — captured ONCE by the class teacher, scoring overall conduct /
 * participation / discipline / attendance / classroom activities.
 *
 * Out of 10, pass at 4. The score is consumed by the Annual Result
 * calculation for ECD–5 classes: even if a student passes every subject,
 * an assessment score below 4 makes the annual result a Fail.
 *
 * Distinct from subject Marks (which are per-(exam, subject, student)) —
 * assessment is exam-agnostic and only applies to primary stages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->decimal('marks_obtained', 5, 2);
            $table->decimal('marks_total', 5, 2)->default(10);
            $table->decimal('passing_marks', 5, 2)->default(4);
            $table->text('remarks')->nullable();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // One row per (student, session) — re-entry updates the row.
            $table->unique(['student_id', 'academic_session_id'], 'assessment_marks_student_session_unique');
            $table->index(['section_id', 'academic_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_marks');
    }
};
