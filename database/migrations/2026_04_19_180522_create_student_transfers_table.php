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
        Schema::create('student_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('to_school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('from_class_id')->constrained('school_classes');
            $table->foreignId('to_class_id')->nullable()->constrained('school_classes');
            $table->foreignId('from_section_id')->constrained('sections');
            $table->foreignId('to_section_id')->nullable()->constrained('sections');
            $table->enum('type', ['intra_school', 'inter_school']);
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('initiated_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('initiated_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_transfers');
    }
};
