<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * exam_subjects.teacher_id — records WHICH teacher was assigned to a
 * subject for a specific exam. Teachers are commonly reassigned between
 * terms (transfer/posting), so the term-wise result sheet needs to show
 * a different name in T-I / T-II / T-III when that happens.
 *
 * Nullable — when left blank the report falls back to the section-level
 * subject_teachers assignment, so existing rows keep working.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->foreignId('teacher_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Teacher assigned to this subject FOR this exam. NULL falls back to the section-level subject_teachers row.');

            $table->index('teacher_id', 'exam_subjects_teacher_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropIndex('exam_subjects_teacher_id_idx');
            $table->dropColumn('teacher_id');
        });
    }
};
