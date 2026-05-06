<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a free-text principal_name column on schools (mirrors exam_officer_name).
     * The earlier setup relied on a User with role=school-admin linked via
     * users.school_id — but most schools don't have a dedicated school-admin
     * user assigned, so the principal name on PDFs was always blank. A direct
     * text field is simpler and matches how the exam officer name is handled.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('principal_name')->nullable()->after('principal_signature');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('principal_name');
        });
    }
};
