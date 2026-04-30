<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'previous_class_id')) {
                $table->unsignedBigInteger('previous_class_id')->nullable()->after('school_class_id');
            }
            if (!Schema::hasColumn('students', 'promotion_status')) {
                $table->string('promotion_status')->nullable();
            }
            if (!Schema::hasColumn('students', 'promoted_at')) {
                $table->date('promoted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'previous_class_id')) {
                $table->dropColumn('previous_class_id');
            }
            if (Schema::hasColumn('students', 'promotion_status')) {
                $table->dropColumn('promotion_status');
            }
            if (Schema::hasColumn('students', 'promoted_at')) {
                $table->dropColumn('promoted_at');
            }
        });
    }
};
