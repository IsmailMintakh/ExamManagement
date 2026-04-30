<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (!Schema::hasColumn('results', 'is_supplementary_eligible')) {
                $table->boolean('is_supplementary_eligible')->default(false);
            }
            if (!Schema::hasColumn('results', 'supplementary_subjects')) {
                $table->json('supplementary_subjects')->nullable(); // array of subject_ids that need supplementary
            }
            if (!Schema::hasColumn('results', 'supplementary_status')) {
                $table->enum('supplementary_status', ['none', 'eligible', 'appeared', 'passed', 'failed'])->default('none');
            }
            if (!Schema::hasColumn('results', 'supplementary_at')) {
                $table->timestamp('supplementary_at')->nullable();
            }
        });

        Schema::table('marks', function (Blueprint $table) {
            if (!Schema::hasColumn('marks', 'is_supplementary')) {
                $table->boolean('is_supplementary')->default(false);
            }
            if (!Schema::hasColumn('marks', 'supplementary_marks')) {
                $table->decimal('supplementary_marks', 8, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            foreach (['is_supplementary_eligible', 'supplementary_subjects', 'supplementary_status', 'supplementary_at'] as $col) {
                if (Schema::hasColumn('results', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('marks', function (Blueprint $table) {
            foreach (['is_supplementary', 'supplementary_marks'] as $col) {
                if (Schema::hasColumn('marks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
