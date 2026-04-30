<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->decimal('total_marks', 8, 2)->default(100);
            $table->decimal('grace_marks', 5, 2)->default(0);
            $table->boolean('is_absent')->default(false);
            $table->text('remarks')->nullable();
            $table->enum('status', ['draft', 'submitted', 'verified'])->default('draft');
            $table->foreignId('entered_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id', 'subject_id'], 'marks_unique');
            $table->index(['exam_id', 'school_class_id', 'section_id']);
        });

        Schema::create('marks_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users');
            $table->enum('status', ['submitted', 'verified', 'rejected'])->default('submitted');
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['exam_id', 'subject_id', 'section_id'], 'marks_submission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks_submissions');
        Schema::dropIfExists('marks');
    }
};
