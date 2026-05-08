<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the algorithm's score breakdown per assignment so admin can ask
 * "why was this teacher picked?" — the popover renders ±10 same-subject,
 * +5 same-class, etc. directly from this JSON.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('substitution_assignments', function (Blueprint $table) {
            $table->json('score_breakdown')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('substitution_assignments', function (Blueprint $table) {
            $table->dropColumn('score_breakdown');
        });
    }
};
