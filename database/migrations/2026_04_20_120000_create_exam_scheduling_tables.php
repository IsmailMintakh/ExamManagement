<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exam rooms per school
        Schema::create('exam_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Room 101", "Hall A"
            $table->integer('capacity')->default(30);
            $table->integer('rows')->default(6);
            $table->integer('cols')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Exam timetable per exam + subject + class
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'subject_id', 'school_class_id'], 'schedule_unique');
        });

        // Exam seat assignments
        Schema::create('exam_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('seat_number'); // e.g., "A-12"
            $table->integer('row_num');
            $table->integer('col_num');
            $table->timestamps();
            $table->unique(['exam_id', 'student_id']);
        });

        // Invigilator duty assignments
        Schema::create('exam_invigilators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the teacher
            $table->enum('role', ['chief', 'invigilator', 'relief'])->default('invigilator');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_invigilators');
        Schema::dropIfExists('exam_seats');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('exam_rooms');
    }
};
