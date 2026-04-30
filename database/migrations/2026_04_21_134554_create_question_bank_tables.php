<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->enum('type', ['mcq', 'short_answer', 'long_answer', 'true_false', 'fill_blank']);
            $table->enum('difficulty', ['easy', 'medium', 'hard']);
            $table->text('question_text');
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->decimal('marks', 5, 2)->default(1);
            $table->string('topic')->nullable();
            $table->integer('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['subject_id', 'school_class_id', 'type', 'difficulty']);
        });

        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('generated_by')->constrained('users');
            $table->string('title');
            $table->string('exam_name')->nullable();
            $table->integer('duration_minutes')->default(180);
            $table->decimal('total_marks', 6, 2);
            $table->text('instructions')->nullable();
            $table->json('sections');
            $table->json('question_ids');
            $table->boolean('shuffle_enabled')->default(true);
            $table->string('set_code')->default('A');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('papers');
        Schema::dropIfExists('questions');
    }
};
