<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A subject is normally class-scoped — every section of the class takes
 * the paper. But some sections don't (e.g. a specialised stream section
 * that skips one subject). This adds an optional per-subject "excluded
 * sections" list so admins can carve out those exceptions without
 * splitting the mapping model.
 *
 * NULL / empty  → default: applies to every section of the class
 * [3, 7]        → applies to every section EXCEPT sections 3 and 7
 *
 * All existing rows implicitly get the default behaviour, so this is
 * additive and non-breaking for the huge majority of exams.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->json('excluded_section_ids')->nullable()->after('school_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropColumn('excluded_section_ids');
        });
    }
};
