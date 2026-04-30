<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'is_transferred')) {
                $table->boolean('is_transferred')->default(false)->after('promotion_status');
            }
            if (!Schema::hasColumn('students', 'transferred_from_school_id')) {
                $table->unsignedBigInteger('transferred_from_school_id')->nullable()->after('is_transferred');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'transferred_from_school_id')) {
                $table->dropColumn('transferred_from_school_id');
            }
            if (Schema::hasColumn('students', 'is_transferred')) {
                $table->dropColumn('is_transferred');
            }
        });
    }
};
