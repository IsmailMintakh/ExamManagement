<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Result publishing workflow.
 *
 * Two parallel timestamps:
 *   - exams.results_published_at: set when an admin publishes the WHOLE exam's
 *     results in one action ("Publish all results for Mid-Term"). Most common
 *     case — a single ceremonial moment.
 *   - results.published_at: per-row override. Allows publishing a single
 *     class/section ahead of others ("Class 10 results are out today, others
 *     tomorrow") and gives us a stable timestamp per row even if the parent
 *     exam is later un-published or edited.
 *
 * Family Portal & public result lookup show ONLY rows where either flag is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->timestamp('results_published_at')->nullable()->after('marks_entry_deadline');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('results_published_at');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn('published_at');
        });
    }
};
