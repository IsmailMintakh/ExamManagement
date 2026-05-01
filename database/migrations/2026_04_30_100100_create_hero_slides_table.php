<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow')->nullable();           // small uppercase tag above title
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();        // background image (storage/app/public/...)
            $table->string('cta_label')->nullable();         // primary button text
            $table->string('cta_url')->nullable();           // primary button link
            $table->string('cta_secondary_label')->nullable();
            $table->string('cta_secondary_url')->nullable();
            $table->string('overlay_color')->default('#0f172a'); // dark base for image overlay
            $table->unsignedTinyInteger('overlay_opacity')->default(60); // 0-100
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
