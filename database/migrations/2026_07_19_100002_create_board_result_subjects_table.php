<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * board_result_subjects — per-subject marks under a board_result.
 *
 * Every FBISE subject splits into theory + practical (practical=0 when
 * the subject has no practical component). Both are stored so we can
 * render the mark sheet exactly like the FBISE gazette does, and so
 * subject-wise analytics (average theory, average practical, pass rate
 * per component) become trivial WHEREs.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('board_result_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_result_id')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained()->cascadeOnDelete();

            // Theory + practical split (either can be 0 for subjects
            // without that component). Total = theory + practical.
            $table->decimal('theory_marks',    5, 2)->default(0);
            $table->decimal('practical_marks', 5, 2)->default(0);
            $table->decimal('total_marks',     6, 2)->default(0);

            // Per-subject max — FBISE usually sets 75-theory + 25-practical
            // for science subjects (100 total), 100-theory for others.
            $table->decimal('theory_max',    5, 2)->default(75);
            $table->decimal('practical_max', 5, 2)->default(25);
            $table->decimal('max_marks',     6, 2)->default(100);

            // Auto-derived by the calculator on save.
            $table->string('grade', 4)->nullable();
            $table->boolean('is_pass')->default(false);
            $table->boolean('is_absent')->default(false);
            $table->unsignedTinyInteger('subject_position')->nullable();  // rank in that subject

            $table->timestamps();

            $table->unique(['board_result_id', 'subject_id'], 'board_result_subject_unique');
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_result_subjects');
    }
};
