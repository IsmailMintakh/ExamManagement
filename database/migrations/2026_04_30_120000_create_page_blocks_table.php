<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 60)->index();   // e.g. 'about', 'academics', 'admissions'
            $table->string('type', 40);                 // 'rich_text', 'mission_vision', 'timeline', etc.
            $table->json('data');                       // type-specific payload
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['page_key', 'sort_order']);
            $table->index(['page_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_blocks');
    }
};
