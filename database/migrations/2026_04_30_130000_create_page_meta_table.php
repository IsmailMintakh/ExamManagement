<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_meta', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 60)->unique();
            $table->string('hero_eyebrow', 120)->nullable();
            $table->string('hero_title', 200);
            $table->string('hero_title_accent', 200)->nullable(); // styled span — second line of title
            $table->string('hero_subtitle', 400)->nullable();
            $table->string('hero_style', 30)->default('emerald-night'); // theme key
            $table->string('meta_title', 200)->nullable();          // <title>
            $table->string('meta_description', 400)->nullable();    // <meta description>
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_meta');
    }
};
