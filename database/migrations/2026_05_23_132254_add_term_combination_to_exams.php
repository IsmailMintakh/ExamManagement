<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Term-based exams + final-term aggregation.
 *
 * `term`                   first | second | final | null (nullable: legacy
 *                          exams stay valid without being part of a term cycle)
 * `combine_previous_terms` only meaningful on a final-term exam. When true,
 *                          result generation merges the same student's
 *                          1st-term + 2nd-term + final-term marks into one
 *                          year-end aggregate using `term_weights`.
 * `term_weights`           {first, second, final} → integers that must sum
 *                          to 100. Default 25 / 25 / 50.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $t) {
            $t->enum('term', ['first', 'second', 'final'])->nullable()->after('academic_session_id');
            $t->boolean('combine_previous_terms')->default(false)->after('term');
            $t->json('term_weights')->nullable()->after('combine_previous_terms');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $t) {
            $t->dropColumn(['term', 'combine_previous_terms', 'term_weights']);
        });
    }
};
