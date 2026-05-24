<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digital signature image for staff — class teachers and subject teachers
 * upload their signature on the Profile page, then it auto-appears in
 * place of the blank line on attendance sheets, award lists, mark sheets,
 * etc. Falls back to the printed line when the user hasn't uploaded one.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('signature_image')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('signature_image'));
    }
};
