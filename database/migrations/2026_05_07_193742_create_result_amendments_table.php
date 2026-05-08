<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Result amendment audit log.
 *
 * Once results are published, sometimes errors surface — a teacher mis-keyed
 * a mark, a paper got re-checked, etc. We don't want to silently overwrite
 * the result row (parents may have already seen the original). This table
 * captures the BEFORE / AFTER snapshot every time a published result is
 * edited, plus the reason and who did it. The Family Portal then renders
 * an "Amended" banner pointing at the latest entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('results')->cascadeOnDelete();
            $table->foreignId('amended_by')->constrained('users');
            $table->string('reason', 500); // required — must say why
            $table->json('before')->nullable();   // snapshot of the result row pre-edit
            $table->json('after')->nullable();    // snapshot post-edit
            $table->timestamps();
            $table->index(['result_id', 'created_at']);
        });

        // Touch the result row so callers can quickly check "has this been
        // amended?" without joining the amendments table on every page render.
        Schema::table('results', function (Blueprint $table) {
            $table->timestamp('last_amended_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('last_amended_at');
        });
        Schema::dropIfExists('result_amendments');
    }
};
