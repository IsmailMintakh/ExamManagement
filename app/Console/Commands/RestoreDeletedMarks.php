<?php

namespace App\Console\Commands;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\MarksSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreDeletedMarks extends Command
{
    protected $signature = 'marks:restore-deleted
                            {--exam= : Restore only marks for this exam id}
                            {--dry : Print what would be restored without changing anything}';

    protected $description = 'Restore every soft-deleted Mark row (admin recovery for the remove-subject / remove-class lossage).';

    public function handle(): int
    {
        $examId = $this->option('exam');
        $dry = (bool) $this->option('dry');

        $base = Mark::onlyTrashed();
        if ($examId) {
            $base->where('exam_id', $examId);
            $examName = Exam::find($examId)?->name ?? "(exam #{$examId})";
            $this->info("Scope: {$examName}");
        } else {
            $this->info('Scope: ALL exams in the system');
        }

        $trashed = (clone $base)->get(['id', 'exam_id', 'subject_id', 'section_id', 'student_id', 'status']);

        if ($trashed->isEmpty()) {
            $this->line('No soft-deleted marks found. Nothing to do.');
            return self::SUCCESS;
        }

        $this->line("Found {$trashed->count()} soft-deleted Mark rows.");

        $liveKeys = Mark::query()
            ->when($examId, fn ($q) => $q->where('exam_id', $examId))
            ->select('exam_id', 'subject_id', 'section_id', 'student_id')
            ->get()
            ->map(fn ($m) => "{$m->exam_id}-{$m->subject_id}-{$m->section_id}-{$m->student_id}")
            ->flip();

        $restorableIds = $trashed
            ->filter(fn ($m) => !$liveKeys->has("{$m->exam_id}-{$m->subject_id}-{$m->section_id}-{$m->student_id}"))
            ->pluck('id');

        $skipped = $trashed->count() - $restorableIds->count();

        if ($skipped > 0) {
            $this->warn("Skipping {$skipped} trashed rows — a newer live entry already exists for that student. Newest entry wins.");
        }

        if ($restorableIds->isEmpty()) {
            $this->line('Nothing to restore after newest-wins filter.');
            return self::SUCCESS;
        }

        if ($dry) {
            $this->info("DRY RUN — would restore {$restorableIds->count()} marks. Re-run without --dry to apply.");
            return self::SUCCESS;
        }

        $this->info("Restoring {$restorableIds->count()} marks...");
        $restored = 0;
        $rebuiltSubmissions = 0;

        DB::transaction(function () use ($restorableIds, &$restored, &$rebuiltSubmissions) {
            $restored = Mark::onlyTrashed()
                ->whereIn('id', $restorableIds)
                ->update(['deleted_at' => null]);

            $submissionTuples = Mark::whereIn('id', $restorableIds)
                ->where('status', 'submitted')
                ->select('exam_id', 'subject_id', 'section_id')
                ->distinct()
                ->get();

            foreach ($submissionTuples as $t) {
                MarksSubmission::updateOrCreate(
                    [
                        'exam_id' => $t->exam_id,
                        'subject_id' => $t->subject_id,
                        'section_id' => $t->section_id,
                    ],
                    [
                        'status' => 'submitted',
                        'submitted_at' => now(),
                    ]
                );
                $rebuiltSubmissions++;
            }
        });

        $this->info("DONE: restored {$restored} marks, rebuilt {$rebuiltSubmissions} submission records.");

        $this->line('');
        $this->line('Reload the marks / results pages — the previously-submitted data is back.');
        return self::SUCCESS;
    }
}
