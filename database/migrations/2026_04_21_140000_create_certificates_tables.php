<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('type', ['merit', 'subject_topper', 'pass', 'special_achievement', 'participation', 'custom']);
            $table->string('title_text')->default('Certificate of Achievement');
            $table->text('body_text');
            $table->string('primary_color', 20)->default('#4f46e5');
            $table->string('accent_color', 20)->default('#f59e0b');
            $table->enum('orientation', ['portrait', 'landscape'])->default('landscape');
            $table->string('border_style', 30)->default('classic');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('certificates_issued', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->constrained('users');
            $table->string('certificate_number')->unique();
            $table->enum('type', ['merit', 'subject_topper', 'pass', 'special_achievement', 'participation', 'custom']);
            $table->json('data');
            $table->string('verification_code', 32)->unique();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
            $table->index(['student_id', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates_issued');
        Schema::dropIfExists('certificate_templates');
    }
};
