<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * board_exams — one record per official Board exam session for a class
 * (e.g. "FBISE SSC 2026 — Class 9").
 *
 * The Board (FBISE) declares results months after the exam. This row
 * lets a school open a "results container" once the gazette is out,
 * pick the students, and fill in their subject-wise marks.
 *
 * Distinct from the internal `exams` table which tracks in-school tests.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('board_exams', function (Blueprint $table) {
            $table->id();
            // Which academic session this board exam belongs to.
            $table->foreignId('academic_session_id')
                ->constrained()->cascadeOnDelete();
            // Which class the exam is for (9th / 10th only in Phase 1).
            $table->foreignId('school_class_id')
                ->constrained('school_classes')->cascadeOnDelete();
            // School-scoping — every board exam belongs to exactly one school,
            // so the DDO can filter across schools and school-admins see only theirs.
            $table->foreignId('school_id')
                ->constrained('schools')->cascadeOnDelete();

            // Board identifier ("FBISE"). Phase 1 hard-codes FBISE but the
            // column exists now so provincial boards can plug in later
            // without a schema change.
            $table->string('board_name', 32)->default('FBISE');
            // Exam level: "SSC" (9th/10th combined), "SSC-I", "SSC-II", etc.
            $table->string('level', 32)->default('SSC');
            // Free-text label the school picks — e.g. "SSC-I Annual 2026".
            $table->string('title');
            // When FBISE officially announced the result (from the gazette).
            $table->date('announced_on')->nullable();

            // Marks-total for the whole exam (for FBISE SSC: usually 505 or
            // 550 depending on group). Stored per exam so a science-group
            // vs arts-group difference is trivial.
            $table->unsignedSmallInteger('total_marks')->default(550);
            // Minimum aggregate marks required to pass (FBISE = 33%).
            $table->unsignedSmallInteger('pass_percentage')->default(33);

            // Free-form notes — supplementary window, revaluation deadline, etc.
            $table->text('notes')->nullable();

            // Locked flag — once results are finalised and shared with parents,
            // the school-admin locks the container so no accidental edits happen.
            $table->boolean('is_locked')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // A school can't have two board exams for the SAME class in the
            // SAME session at the same level.
            $table->unique(['school_id', 'school_class_id', 'academic_session_id', 'level'], 'board_exams_unique');
            $table->index(['school_id', 'academic_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_exams');
    }
};
