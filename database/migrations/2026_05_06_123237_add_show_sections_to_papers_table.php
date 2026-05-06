<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            // When false, the printed paper shows questions in one continuous
            // numbered list with no section headings. Useful for primary
            // classes (Nursery / Prep) where sectioning is unhelpful.
            $table->boolean('show_sections')->default(true)->after('shuffle_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->dropColumn('show_sections');
        });
    }
};
