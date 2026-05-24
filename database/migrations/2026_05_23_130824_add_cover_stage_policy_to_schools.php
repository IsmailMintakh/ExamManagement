<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-school adjustment policy.
 *
 *   strict — substitute must teach the same stage as the absent class.
 *            Pre-Primary/Primary stay separate from Middle/High/Higher Sec.
 *   open   — anyone qualified can cover any stage (cluster-school style).
 *
 * Default 'strict' because that's the safer (and more commonly requested)
 * behaviour. School admins can flip to 'open' from the Class Adjustments page.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $t) {
            $t->enum('cover_stage_policy', ['strict', 'open'])
                ->default('strict')
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('schools', fn (Blueprint $t) => $t->dropColumn('cover_stage_policy'));
    }
};
