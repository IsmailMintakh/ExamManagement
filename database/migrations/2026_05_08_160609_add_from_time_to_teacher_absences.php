<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds partial-day absence support to teacher_absences.
 *
 *   from_time = NULL  → absent the entire day (existing behavior)
 *   from_time = HH:MM → present until this time, absent from this time onwards
 *
 * The substitution engine treats periods whose start time is >= from_time as
 * needing cover; earlier periods are taught as scheduled.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('teacher_absences', function (Blueprint $table) {
            $table->time('from_time')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_absences', function (Blueprint $table) {
            $table->dropColumn('from_time');
        });
    }
};
