<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->index('user_id');
            }
            if (!Schema::hasColumn('students', 'parent_user_id')) {
                $table->unsignedBigInteger('parent_user_id')->nullable()->after('user_id');
                $table->index('parent_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'parent_user_id')) {
                $table->dropIndex(['parent_user_id']);
                $table->dropColumn('parent_user_id');
            }
            if (Schema::hasColumn('students', 'user_id')) {
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
