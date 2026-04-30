<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();

            // Passing rules (JSON for flexibility)
            $table->decimal('total_marks', 8, 2)->default(100);
            $table->decimal('passing_marks', 8, 2)->default(33);
            $table->decimal('passing_percentage', 5, 2)->default(33.00);
            $table->integer('min_subjects_to_pass')->nullable();
            $table->boolean('main_subjects_must_pass')->default(false);
            $table->boolean('all_subjects_must_pass')->default(false);
            $table->decimal('grace_marks', 5, 2)->default(0);
            $table->integer('grace_marks_max_subjects')->default(0);
            $table->string('position_calculation')->default('section'); // section, class, school
            $table->json('passing_rules')->nullable();
            $table->json('combination_rules')->nullable();

            // Applicable scope
            $table->boolean('apply_to_all_schools')->default(true);
            $table->json('applicable_class_ids')->nullable();

            $table->enum('status', ['draft', 'published', 'marks_entry', 'processing', 'completed', 'archived'])->default('draft');
            $table->timestamp('marks_entry_deadline')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('exam_schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'school_id']);
        });

        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_marks', 8, 2)->default(100);
            $table->decimal('passing_marks', 8, 2)->default(33);
            $table->date('exam_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'subject_id', 'school_class_id'], 'exam_subject_class_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exam_schools');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_types');
    }
};
