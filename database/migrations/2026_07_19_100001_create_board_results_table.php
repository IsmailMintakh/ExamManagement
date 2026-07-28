<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * board_results — one row per student per board_exam.
 *
 * The parent record for a student's official Board mark sheet. Per-subject
 * marks live in `board_result_subjects` (child table). Overall totals,
 * percentage, grade, division, and pass/fail are stored here so reports
 * don't have to recompute them every load.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('board_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_exam_id')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained()->cascadeOnDelete();

            // FBISE-issued roll number for this student — printed on the
            // admit card. Distinct from our internal admission_no.
            $table->string('board_roll_no', 32)->nullable();

            // Aggregate figures — auto-populated by the calculator service
            // whenever a subject row is saved.
            $table->decimal('total_obtained', 6, 2)->default(0);
            $table->decimal('total_max',       6, 2)->default(0);
            $table->decimal('percentage',      5, 2)->default(0);   // 0.00 - 100.00
            // FBISE grade scale: A1, A, B, C, D, E, F. Nullable when the
            // student hasn't been graded yet (marks entered but no
            // calculation run — shouldn't happen but safe default).
            $table->string('grade', 4)->nullable();
            // FBISE division: 1st, 2nd, 3rd, Fail, or Supply (if <=2 fails).
            $table->string('division', 16)->nullable();

            // Overall pass/fail — true only when every-subject-pass AND
            // aggregate >= pass_percentage. Stored so filters are cheap.
            $table->boolean('is_pass')->default(false);
            // Failed in ≤2 subjects → student can appear in supplementary.
            // Failed in ≥3 → full fail (must repeat). Stored so the fail
            // list vs supply list on reports is a straight WHERE.
            $table->boolean('is_supplementary')->default(false);

            // Class rank within this board_exam (1 = topper). Populated by
            // the calculator after every save so leaderboards are cheap.
            $table->unsignedInteger('position')->nullable();

            $table->text('remarks')->nullable();

            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // A student can only have ONE result per board exam.
            $table->unique(['board_exam_id', 'student_id'], 'board_results_unique');
            $table->index(['board_exam_id', 'is_pass']);
            $table->index(['board_exam_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_results');
    }
};
