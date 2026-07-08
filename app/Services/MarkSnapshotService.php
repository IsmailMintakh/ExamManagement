<?php

namespace App\Services;

use App\Models\Mark;
use App\Models\MarkSnapshot;
use App\Models\MarksSubmission;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Marks backup / restore. Captures the full state of a (exam, subject,
 * section) paper before anything destructive happens, and lets an admin
 * or teacher roll back to any snapshot when live rows go missing.
 *
 * Every destructive Mark operation in the app should call ::capture()
 * with a descriptive trigger BEFORE mutating. That way if the operation
 * turns out to be wrong, ::restore() reconstructs the state exactly.
 */
class MarkSnapshotService
{
    /**
     * Keep the last N snapshots per paper. Older ones are pruned when a
     * new snapshot is written. Twenty is enough to cover a whole edit
     * session plus a few incidents without ballooning the table.
     */
    public const RETENTION_PER_PAPER = 20;

    /**
     * Take a snapshot of every Mark row (live + trashed) for the given
     * paper. Returns the new MarkSnapshot record, or null when there's
     * nothing worth capturing (no marks exist yet).
     */
    public static function capture(
        int $examId,
        int $subjectId,
        int $sectionId,
        string $trigger,
        ?int $userId = null,
        ?string $notes = null,
    ): ?MarkSnapshot {
        // withTrashed so a snapshot taken while marks are soft-deleted
        // still captures them — useful when a restore was attempted but
        // the recovery flow itself gets rolled back.
        $marks = Mark::withTrashed()
            ->where('exam_id', $examId)
            ->where('subject_id', $subjectId)
            ->where('section_id', $sectionId)
            ->get([
                'student_id', 'exam_subject_id', 'school_class_id',
                'marks_obtained', 'total_marks', 'grace_marks',
                'is_absent', 'remarks', 'status', 'submitted_at',
                'deleted_at',
            ]);

        if ($marks->isEmpty()) {
            return null;
        }

        $sectionModel = Section::find($sectionId);

        $snap = MarkSnapshot::create([
            'exam_id' => $examId,
            'subject_id' => $subjectId,
            'section_id' => $sectionId,
            'school_class_id' => $sectionModel?->school_class_id,
            'taken_at' => now(),
            'taken_by' => $userId,
            'trigger' => $trigger,
            'payload' => $marks->map(fn ($m) => [
                'student_id' => $m->student_id,
                'exam_subject_id' => $m->exam_subject_id,
                'school_class_id' => $m->school_class_id,
                'marks_obtained' => (float) $m->marks_obtained,
                'total_marks' => (float) $m->total_marks,
                'grace_marks' => (float) $m->grace_marks,
                'is_absent' => (bool) $m->is_absent,
                'remarks' => $m->remarks,
                'status' => $m->status,
                'submitted_at' => $m->submitted_at?->toIso8601String(),
                'was_trashed' => $m->deleted_at !== null,
            ])->values()->all(),
            'student_count' => $marks->count(),
            'notes' => $notes,
        ]);

        static::prune($examId, $subjectId, $sectionId);

        return $snap;
    }

    /**
     * Restore a paper to a snapshot. Takes a fresh snapshot of the current
     * state first (trigger=pre_restore) so the restore itself is undoable,
     * then overwrites the Mark rows from the snapshot payload.
     * Returns the number of Mark rows restored.
     */
    public static function restore(MarkSnapshot $snap, ?int $userId = null): int
    {
        // Save a "current state" snapshot before touching anything —
        // if the restore turns out to be wrong, we can undo it.
        static::capture(
            $snap->exam_id, $snap->subject_id, $snap->section_id,
            'pre_restore', $userId,
            "auto-taken before restoring snapshot #{$snap->id}"
        );

        $restored = 0;
        DB::transaction(function () use ($snap, $userId, &$restored) {
            foreach ($snap->payload as $row) {
                $mark = Mark::withTrashed()
                    ->where('exam_id', $snap->exam_id)
                    ->where('subject_id', $snap->subject_id)
                    ->where('section_id', $snap->section_id)
                    ->where('student_id', $row['student_id'])
                    ->first();

                if (!$mark) {
                    $mark = new Mark([
                        'exam_id' => $snap->exam_id,
                        'subject_id' => $snap->subject_id,
                        'section_id' => $snap->section_id,
                        'student_id' => $row['student_id'],
                    ]);
                }

                $mark->fill([
                    'exam_id' => $snap->exam_id,
                    'subject_id' => $snap->subject_id,
                    'section_id' => $snap->section_id,
                    'student_id' => $row['student_id'],
                    'exam_subject_id' => $row['exam_subject_id'],
                    'school_class_id' => $row['school_class_id'] ?? $snap->school_class_id,
                    'marks_obtained' => $row['marks_obtained'],
                    'total_marks' => $row['total_marks'],
                    'grace_marks' => $row['grace_marks'],
                    'is_absent' => $row['is_absent'],
                    'remarks' => $row['remarks'],
                    'status' => $row['status'],
                    'entered_by' => $userId,
                    'submitted_at' => !empty($row['submitted_at']) ? \Carbon\Carbon::parse($row['submitted_at']) : null,
                ]);
                // Even if the snapshot captured a trashed row, restore
                // it live — the whole point of "restore" is to bring
                // marks back into view, not preserve their deletion.
                $mark->deleted_at = null;
                $mark->save();
                $restored++;
            }

            // If any restored row was submitted, refresh the MarksSubmission
            // row so the UI shows "Submitted" again.
            $hasSubmitted = collect($snap->payload)->contains(fn ($r) => ($r['status'] ?? '') === 'submitted');
            if ($hasSubmitted) {
                MarksSubmission::updateOrCreate(
                    ['exam_id' => $snap->exam_id, 'subject_id' => $snap->subject_id, 'section_id' => $snap->section_id],
                    ['school_class_id' => $snap->school_class_id, 'status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => $userId]
                );
            }
        });

        return $restored;
    }

    /**
     * Recent snapshots for a paper, newest first.
     */
    public static function forPaper(int $examId, int $subjectId, int $sectionId, int $limit = 20)
    {
        return MarkSnapshot::where('exam_id', $examId)
            ->where('subject_id', $subjectId)
            ->where('section_id', $sectionId)
            ->with('takenBy:id,name')
            ->orderByDesc('taken_at')
            ->limit($limit)
            ->get();
    }

    protected static function prune(int $examId, int $subjectId, int $sectionId): void
    {
        $ids = MarkSnapshot::where('exam_id', $examId)
            ->where('subject_id', $subjectId)
            ->where('section_id', $sectionId)
            ->orderByDesc('taken_at')
            ->skip(static::RETENTION_PER_PAPER)
            ->take(1000)
            ->pluck('id');
        if ($ids->isNotEmpty()) {
            MarkSnapshot::whereIn('id', $ids)->delete();
        }
    }
}
