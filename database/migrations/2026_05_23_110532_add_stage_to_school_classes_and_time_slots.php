<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add educational stage to classes and time slots.
 *
 * Schools commonly run different bell schedules per stage — ECD/Pre-Primary
 * may have 4 short periods and finish early; Primary 6 periods; Middle/High
 * 7-8 periods. Without a stage column the bell schedule is school-wide
 * which forces the wrong rhythm on the smaller kids.
 *
 * Columns added (both nullable for back-compat):
 *   school_classes.stage  → groups classes by stage for UI + reports
 *   time_slots.stage      → NULL = applies to every class in the school;
 *                            a value = applies only to that stage
 *
 * Existing classes are auto-stage based on name (best-effort backfill).
 */
return new class extends Migration {
    public function up(): void
    {
        $stages = ['pre_primary', 'primary', 'middle', 'secondary', 'higher_secondary'];

        Schema::table('school_classes', function (Blueprint $t) use ($stages) {
            $t->enum('stage', $stages)->nullable()->after('numeric_name')->index();
        });

        Schema::table('time_slots', function (Blueprint $t) use ($stages) {
            $t->enum('stage', $stages)->nullable()->after('type')->index();
        });

        // Backfill: assign a stage to existing classes by name match.
        $byName = [
            'pre_primary' => ['ecd', 'ecd-i', 'ecd-ii', 'ecd i', 'ecd ii', 'nursery', 'prep', 'play group', 'playgroup', 'kg', 'kindergarten'],
            'primary' => ['one', 'two', 'three', 'four', 'fifth', 'five', 'class one', 'class two', 'class three', 'class four', 'class five'],
            'middle' => ['sixth', 'seventh', 'eight', 'eighth', 'six', 'seven', 'class six', 'class seven', 'class eight'],
            'secondary' => ['nine', 'ninth', 'ten', 'tenth', 'matric', 'ssc', 'class nine', 'class ten'],
            'higher_secondary' => ['first year', 'second year', 'eleventh', 'twelfth', 'fa', 'fsc', 'ics', 'icom', 'hssc', 'inter', 'intermediate'],
        ];

        $rows = DB::table('school_classes')->whereNull('stage')->get(['id', 'name']);
        foreach ($rows as $row) {
            $needle = strtolower(trim($row->name));
            foreach ($byName as $stage => $candidates) {
                foreach ($candidates as $c) {
                    if ($needle === $c || str_contains($needle, $c)) {
                        DB::table('school_classes')->where('id', $row->id)->update(['stage' => $stage]);
                        continue 3;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('time_slots', fn (Blueprint $t) => $t->dropColumn('stage'));
        Schema::table('school_classes', fn (Blueprint $t) => $t->dropColumn('stage'));
    }
};
