<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional per-exam Exam Controller — a teacher the Principal/DDO designates
     * for this specific exam. Their name appears as the "Examination Officer" on
     * the date sheet, admit cards, and result paperwork. Falls back to the
     * school's default exam_officer_name when null.
     *
     * nullOnDelete keeps the exam alive if the assigned teacher is removed,
     * just clears the assignment.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('exam_controller_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_controller_id');
        });
    }
};
