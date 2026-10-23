<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The owner's own design of the card: a theme (colours, fonts, corners) and
     * an ordered list of blocks. Empty means the card uses its template.
     */
    public function up(): void
    {
        Schema::table('vcards', function (Blueprint $table) {
            $table->json('design')->nullable()->after('section_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vcards', function (Blueprint $table) {
            $table->dropColumn('design');
        });
    }
};
