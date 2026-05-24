<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-period absence support.
 *
 * `from_time` already handles "left at 10:30" → cover every period from
 * that time onwards. But sometimes a teacher misses periods 3 and 4, then
 * comes back for 5, then leaves again for 7 — `from_time` can't model
 * that. `absent_slot_ids` lets admin tick exactly which periods to cover.
 *
 * When NULL → fall back to from_time (or full day if from_time is null).
 * When a JSON array → cover only those time_slot_ids; ignore everything
 *                      not in the list (teacher took those classes herself).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('teacher_absences', function (Blueprint $table) {
            $table->json('absent_slot_ids')->nullable()->after('from_time');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_absences', fn (Blueprint $t) => $t->dropColumn('absent_slot_ids'));
    }
};
