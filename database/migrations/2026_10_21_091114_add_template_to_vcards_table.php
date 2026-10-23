<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The card design of the card builder: one of four layouts in one brand
     * colour. Empty on cards saved before; those take a look from their old theme.
     */
    public function up(): void
    {
        Schema::table('vcards', function (Blueprint $table) {
            $table->string('template', 20)->nullable()->after('theme')->comment('classic, modern, minimal or bold');
            $table->string('accent_color', 7)->nullable()->after('template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vcards', function (Blueprint $table) {
            $table->dropColumn(['template', 'accent_color']);
        });
    }
};
