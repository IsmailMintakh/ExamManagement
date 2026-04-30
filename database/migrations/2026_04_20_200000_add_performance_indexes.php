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
        $drops = [
            'results' => ['results_session_passed_idx', 'results_section_position_idx'],
            'marks' => ['marks_student_exam_idx', 'marks_status_idx'],
            'exams' => ['exams_status_session_idx'],
            'notifications' => ['notifications_notifiable_read_idx'],
            'subject_teachers' => ['subject_teachers_user_active_idx'],
            'students' => ['students_status_session_idx'],
        ];

        foreach ($drops as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexes) {
                foreach ($indexes as $idx) {
                    if ($this->indexExists($tableName, $idx)) {
                        $table->dropIndex($idx);
                    }
                }
            });
        }
    }

    /**
     * DB-agnostic index existence check. Works on MySQL, MariaDB, PostgreSQL, and SQLite.
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes($table);
            return array_key_exists(strtolower($indexName), array_change_key_case($indexes, CASE_LOWER));
        } catch (\Throwable $e) {
            // Fallback: if DBAL isn't available, query INFORMATION_SCHEMA / pragma directly
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                $rows = \DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?", [$table, $indexName]);
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $rows = \DB::select(
                    'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
                    [$table, $indexName]
                );
            } elseif ($driver === 'pgsql') {
                $rows = \DB::select('SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1', [$table, $indexName]);
            } else {
                return false; // unknown driver — skip the optimization, indexes are duplicates only if already defined
            }
            return !empty($rows);
        }
    }
};
