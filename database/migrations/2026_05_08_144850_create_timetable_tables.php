<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timetable + substitution system.
 *
 * 4 tables, each scoped per school so a multi-school district can have
 * different bell schedules + period structures (some schools run 8 periods,
 * some 6, primary schools don't have a Period 7, etc).
 *
 * The `weekdays` JSON column on time_slots is the Friday-half-day workhorse:
 * a slot with weekdays = ['mon','tue','wed','thu','sat'] simply doesn't render
 * on Friday. No special-case branching elsewhere — Friday picks up the slots
 * whose `weekdays` JSON contains 'fri'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Bell schedule: one row per period / break / lunch / assembly ───
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);                        // "Period 1", "Break", "Lunch"
            $table->enum('type', ['period', 'break', 'lunch', 'assembly'])->default('period');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('weekdays')->nullable();              // ["mon","tue","wed","thu","fri","sat"] — null = all 6
            $table->timestamps();
            $table->index(['school_id', 'sort_order']);
        });

        // ─── The master matrix: one row per (section × weekday × period) ───
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->enum('weekday', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']);
            $table->foreignId('time_slot_id')->constrained('time_slots')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('room', 30)->nullable();
            $table->timestamps();
            $table->unique(['section_id', 'weekday', 'time_slot_id'], 'tt_section_day_slot_unique');
            // Used by substitution algorithm to ask "is teacher X free in slot Y on day Z"
            $table->index(['teacher_id', 'weekday', 'time_slot_id'], 'tt_teacher_day_slot_idx');
        });

        // ─── Daily teacher absences ───
        Schema::create('teacher_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('absent_on');
            $table->string('reason', 200)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'absent_on']);
            $table->index('absent_on');
        });

        // ─── Generated cover assignments ───
        Schema::create('substitution_assignments', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('timetable_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_teacher_id')->constrained('users');
            $table->foreignId('substitute_teacher_id')->constrained('users');
            $table->enum('status', ['suggested', 'confirmed', 'declined'])->default('suggested');
            $table->string('notes', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['date', 'substitute_teacher_id']);
            // Prevents double-booking the substitute in the same time slot
            // (if the same algorithm somehow runs twice, the second insert
            // is a no-op rather than a duplicate row).
            $table->unique(['date', 'timetable_entry_id'], 'sub_date_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitution_assignments');
        Schema::dropIfExists('teacher_absences');
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('time_slots');
    }
};
