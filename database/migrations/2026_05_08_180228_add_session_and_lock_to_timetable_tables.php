<?php

use App\Models\AcademicSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — data integrity for the timetable subsystem.
 *
 * Adds:
 *   - timetable_entries.academic_session_id     — scope per session
 *   - teacher_absences.academic_session_id      — same
 *   - teacher_absences.was_backdated            — admin marked yesterday's absence today
 *   - substitution_assignments.academic_session_id
 *   - sections.timetable_locked                 — prevents accidental edits
 *   - sections.timetable_locked_at              — audit trail
 *   - sections.timetable_locked_by              — who locked it
 *
 * Backfill: every existing row gets the current academic session id, so
 * legacy data continues to work without manual cleanup.
 */
return new class extends Migration {
    public function up(): void
    {
        $currentSessionId = AcademicSession::currentSession()?->id;

        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->foreignId('academic_session_id')
                ->nullable()
                ->after('section_id')
                ->constrained('academic_sessions')
                ->nullOnDelete();
            $table->index(['academic_session_id', 'section_id'], 'tt_entries_session_section_idx');
        });

        Schema::table('teacher_absences', function (Blueprint $table) {
            $table->foreignId('academic_session_id')
                ->nullable()
                ->after('user_id')
                ->constrained('academic_sessions')
                ->nullOnDelete();
            $table->boolean('was_backdated')->default(false)->after('from_time');
            $table->index(['academic_session_id', 'absent_on'], 'tt_absences_session_date_idx');
        });

        Schema::table('substitution_assignments', function (Blueprint $table) {
            $table->foreignId('academic_session_id')
                ->nullable()
                ->after('id')
                ->constrained('academic_sessions')
                ->nullOnDelete();
            $table->index(['academic_session_id', 'date'], 'tt_subs_session_date_idx');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->boolean('timetable_locked')->default(false)->after('class_teacher_id');
            $table->timestamp('timetable_locked_at')->nullable();
            $table->foreignId('timetable_locked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        // ─── Backfill existing rows with current session ───
        if ($currentSessionId) {
            DB::table('timetable_entries')->whereNull('academic_session_id')
                ->update(['academic_session_id' => $currentSessionId]);
            DB::table('teacher_absences')->whereNull('academic_session_id')
                ->update(['academic_session_id' => $currentSessionId]);
            DB::table('substitution_assignments')->whereNull('academic_session_id')
                ->update(['academic_session_id' => $currentSessionId]);
        }
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropIndex('tt_entries_session_section_idx');
            $table->dropConstrainedForeignId('academic_session_id');
        });
        Schema::table('teacher_absences', function (Blueprint $table) {
            $table->dropIndex('tt_absences_session_date_idx');
            $table->dropColumn('was_backdated');
            $table->dropConstrainedForeignId('academic_session_id');
        });
        Schema::table('substitution_assignments', function (Blueprint $table) {
            $table->dropIndex('tt_subs_session_date_idx');
            $table->dropConstrainedForeignId('academic_session_id');
        });
        Schema::table('sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('timetable_locked_by');
            $table->dropColumn(['timetable_locked', 'timetable_locked_at']);
        });
    }
};
