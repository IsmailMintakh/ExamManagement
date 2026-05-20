<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipe demo exam data so the school can start fresh.
 *
 * KEEPS: students, users, subjects, classes, sections, subject_teachers,
 *        time_slots, timetable_entries, teacher_absences, substitution_assignments,
 *        academic_sessions, schools, site settings, lesson plans, etc.
 *
 * REMOVES: exams + all related rows (exam_subjects, exam_schedules,
 *          marks, marks_submissions, results, result_submissions,
 *          result_amendments, exam_seats, exam_invigilators,
 *          issued_certificates, exam_schools pivot). Optionally also
 *          exam_rooms with --with-rooms.
 */
class ResetExamData extends Command
{
    protected $signature = 'demo:reset-exams
        {--force : Skip the confirmation prompt}
        {--with-rooms : Also wipe exam_rooms (physical room records)}
        {--dry-run : Show what would be deleted, change nothing}';

    protected $description = 'Wipe demo exam/result/marks data, keep setup (students, teachers, timetable, etc.)';

    /** Tables to truncate, in safe FK order. */
    protected array $tables = [
        'issued_certificates',
        'result_amendments',
        'result_submissions',
        'results',
        'marks_submissions',
        'marks',
        'exam_invigilators',
        'exam_seats',
        'exam_schedules',
        'exam_subjects',
        'exam_schools',
        'exams',
    ];

    public function handle(): int
    {
        $tables = $this->tables;
        if ($this->option('with-rooms')) {
            $tables[] = 'exam_rooms';
        }

        // ── Show counts ──
        $rows = [];
        foreach ($tables as $t) {
            $rows[] = [$t, Schema::hasTable($t) ? DB::table($t)->count() : '—'];
        }
        $this->info('Will REMOVE the following:');
        $this->table(['Table', 'Rows'], $rows);

        $this->newLine();
        $this->line('<fg=green>Will KEEP untouched:</> students, users, subjects, classes, sections, subject_teachers, time_slots, timetable_entries, teacher_absences, substitution_assignments, academic_sessions, schools.');
        if (!$this->option('with-rooms')) {
            $this->line('<fg=yellow>Note:</> exam_rooms are kept (use --with-rooms to wipe them too).');
        }
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('Dry run — no changes made.');
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('This is irreversible. Proceed?', false)) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        // ── Wipe in a transaction with FK checks off ──
        $deleted = [];
        try {
            Schema::disableForeignKeyConstraints();
            foreach ($tables as $t) {
                if (!Schema::hasTable($t)) continue;
                $before = DB::table($t)->count();
                DB::table($t)->truncate();
                $deleted[$t] = $before;
            }
            Schema::enableForeignKeyConstraints();
        } catch (\Throwable $e) {
            Schema::enableForeignKeyConstraints();
            $this->error('Failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done. Removed:');
        $this->table(['Table', 'Rows removed'], collect($deleted)->map(fn ($n, $t) => [$t, $n])->values()->all());

        return self::SUCCESS;
    }
}
