<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * board_exam_subjects — per-subject template under one board_exam.
 *
 * When the admin creates a board exam, we auto-seed one row per subject
 * that maps to the class (defaults: theory_max=75, practical_max=25,
 * pass_percentage=33). They can then tune the numbers once for the
 * whole exam; the per-student entry form + batch grid pre-fill from
 * this template so teachers don't set the max cell-by-cell.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('board_exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->decimal('theory_max',    5, 2)->default(75);
            $table->decimal('practical_max', 5, 2)->default(25);
            // Optional per-subject pass % override. NULL = use the exam-level
            // pass_percentage.
            $table->decimal('pass_percentage', 5, 2)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['board_exam_id', 'subject_id'], 'board_exam_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_exam_subjects');
    }
};
