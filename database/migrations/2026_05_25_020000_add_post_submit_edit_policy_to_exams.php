<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-controlled "edit marks after submission" feature.
 *
 *   - post_submit_edit_policy: 'none' (default) blocks all post-submission edits;
 *     'all' unlocks every (subject, section) on this exam; 'specific' restricts
 *     unlock to the pairs listed in post_submit_edit_scope.
 *   - post_submit_edit_scope: JSON list of [{school_class_id, section_id}]
 *     tuples granted edit permission. Only consulted when policy = 'specific'.
 *
 * The policy is intentionally per-exam — admins commonly want to lock a Final
 * exam tight while leaving a Mid-Term mutable. A separate "applies to specific
 * exams" option from the spec is implicit: just leave most exams on 'none'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('post_submit_edit_policy', 16)
                ->default('none')
                ->after('is_locked');
            $table->json('post_submit_edit_scope')
                ->nullable()
                ->after('post_submit_edit_policy');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['post_submit_edit_policy', 'post_submit_edit_scope']);
        });
    }
};
