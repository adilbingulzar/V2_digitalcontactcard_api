<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Admin-editable UI translations (legacy `tbl_language`). `key` is the
     * English source text, as in the old app.
     */
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 10);
            // Binary collation: "Email" and "email" are different keys.
            $table->string('key', 500)->collation('utf8mb4_bin');
            $table->text('text')->nullable();
            $table->string('group', 100)->nullable()->comment('Page or area the text belongs to');
            $table->timestamps();

            $table->unique(['locale', 'key'], 'translations_locale_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
