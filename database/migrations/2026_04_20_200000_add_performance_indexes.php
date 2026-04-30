<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hot query: Result::where('academic_session_id', ?)->where('is_passed', ?)
        Schema::table('results', function (Blueprint $table) {
            if (!$this->indexExists('results', 'results_session_passed_idx')) {
                $table->index(['academic_session_id', 'is_passed'], 'results_session_passed_idx');
            }
            if (!$this->indexExists('results', 'results_section_position_idx')) {
                $table->index(['section_id', 'position'], 'results_section_position_idx');
            }
        });

        // Hot query: Mark::where('exam_id', ?)->where('student_id', ?)
        Schema::table('marks', function (Blueprint $table) {
            if (!$this->indexExists('marks', 'marks_student_exam_idx')) {
                $table->index(['student_id', 'exam_id'], 'marks_student_exam_idx');
            }
            if (!$this->indexExists('marks', 'marks_status_idx')) {
                $table->index('status', 'marks_status_idx');
            }
        });

        // Exam status filter (very common)
        Schema::table('exams', function (Blueprint $table) {
            if (!$this->indexExists('exams', 'exams_status_session_idx')) {
                $table->index(['status', 'academic_session_id'], 'exams_status_session_idx');
            }
        });

        // Notifications: unread filter
        Schema::table('notifications', function (Blueprint $table) {
            if (!$this->indexExists('notifications', 'notifications_notifiable_read_idx')) {
                $table->index(['notifiable_id', 'notifiable_type', 'read_at'], 'notifications_notifiable_read_idx');
            }
        });

        // Subject teacher lookups
        Schema::table('subject_teachers', function (Blueprint $table) {
            if (!$this->indexExists('subject_teachers', 'subject_teachers_user_active_idx')) {
                $table->index(['user_id', 'is_active'], 'subject_teachers_user_active_idx');
            }
        });

        // Students: common filter
        Schema::table('students', function (Blueprint $table) {
            if (!$this->indexExists('students', 'students_status_session_idx')) {
                $table->index(['status', 'academic_session_id'], 'students_status_session_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropIndexIfExists('results_session_passed_idx');
            $table->dropIndexIfExists('results_section_position_idx');
        });
        Schema::table('marks', function (Blueprint $table) {
            $table->dropIndexIfExists('marks_student_exam_idx');
            $table->dropIndexIfExists('marks_status_idx');
        });
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndexIfExists('exams_status_session_idx');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndexIfExists('notifications_notifiable_read_idx');
        });
        Schema::table('subject_teachers', function (Blueprint $table) {
            $table->dropIndexIfExists('subject_teachers_user_active_idx');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndexIfExists('students_status_session_idx');
        });
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $result = \DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?", [$table, $indexName]);
        return !empty($result);
    }
};
