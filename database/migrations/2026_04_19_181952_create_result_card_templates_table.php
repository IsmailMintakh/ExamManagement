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
        Schema::create('result_card_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('academic_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete(); // null = applies to all schools
            $table->text('html_template'); // Blade-style HTML template
            $table->json('placeholders')->nullable(); // JSON list of available placeholders for documentation
            $table->string('header_image')->nullable(); // path to uploaded letterhead image
            $table->string('footer_text')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_card_templates');
    }
};
