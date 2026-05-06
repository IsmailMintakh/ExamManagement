<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an exam_officer_signature image upload to schools.
     * Used on date sheets, admit cards, and other exam paperwork that needs
     * a second authoritative signature alongside the Principal's.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('exam_officer_signature')->nullable()->after('school_stamp');
            $table->string('exam_officer_name')->nullable()->after('exam_officer_signature');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['exam_officer_signature', 'exam_officer_name']);
        });
    }
};
