<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot table for the "marks disappeared, teachers lost their work"
     * recurring incident. Every destructive or mutating action on Mark rows
     * (submit, edit, force-submit, remove-subject, remove-class, admin
     * override) writes a full payload snapshot here BEFORE touching the
     * live rows. Teachers can browse snapshots for a paper and restore any
     * one of them if something goes wrong.
     *
     * Non-destructive by design: restoring writes back into the Marks table
     * and takes a fresh snapshot first, so every restore is also reversible.
     */
    public function up(): void
    {
        Schema::create('mark_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->timestamp('taken_at')->useCurrent();
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            // What triggered the snapshot — audit-friendly enum.
            //   pre_autosave, pre_store, pre_submit, post_submit,
            //   pre_submit_drafts, pre_remove_subject, pre_remove_class,
            //   pre_admin_delete, pre_restore, manual
            $table->string('trigger', 40);
            // Full payload: [ {student_id, marks_obtained, is_absent, remarks, status, exam_subject_id, total_marks, grace_marks}, ... ]
            $table->json('payload');
            $table->unsignedSmallInteger('student_count')->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            // Fast lookup for the "restore for this paper" flow.
            $table->index(['exam_id', 'subject_id', 'section_id', 'taken_at'], 'ms_paper_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mark_snapshots');
    }
};
