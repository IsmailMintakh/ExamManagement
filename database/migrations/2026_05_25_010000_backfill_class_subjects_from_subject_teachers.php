<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Heal any (class, subject) pairs that are being actively TAUGHT (have a
 * subject_teachers row) but are missing from the class_subjects curriculum
 * pivot. The exam Create form reads from class_subjects to decide which
 * subjects to auto-tick when an admin picks a class — without this backfill,
 * subjects like "Drawing on Class 8" silently disappear from the form even
 * though a teacher is assigned to teach them.
 *
 * Going forward, TeacherAssignmentController::store/bulkStore upserts into
 * class_subjects too, so the two tables stay in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('subject_teachers as st')
            ->leftJoin('class_subjects as cs', function ($j) {
                $j->on('st.subject_id', '=', 'cs.subject_id')
                  ->on('st.school_class_id', '=', 'cs.school_class_id');
            })
            ->whereNull('cs.id')
            ->where('st.is_active', true)
            ->select('st.subject_id', 'st.school_class_id')
            ->distinct()
            ->get();

        $rows = $missing->map(fn ($m) => [
            'school_class_id' => $m->school_class_id,
            'subject_id' => $m->subject_id,
            'is_active' => true,
        ])->all();

        if (!empty($rows)) {
            DB::table('class_subjects')->upsert(
                $rows,
                ['school_class_id', 'subject_id'],
                ['is_active'],
            );
        }
    }

    public function down(): void
    {
        // No-op — we can't reliably know which rows we backfilled vs which
        // existed before, and we'd never want to delete curriculum on rollback.
    }
};
