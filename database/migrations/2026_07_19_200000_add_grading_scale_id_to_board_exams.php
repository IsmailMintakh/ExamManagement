<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * board_exams.grading_scale_id — optional link to a school's
 * GradingScale. When set, the calculator reads bands from that scale
 * instead of the hardcoded FBISE defaults. NULL keeps the default
 * behaviour so existing rows are safe.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('board_exams', function (Blueprint $table) {
            $table->foreignId('grading_scale_id')
                ->nullable()
                ->after('pass_percentage')
                ->constrained('grading_scales')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('board_exams', function (Blueprint $table) {
            $table->dropForeign(['grading_scale_id']);
            $table->dropColumn('grading_scale_id');
        });
    }
};
