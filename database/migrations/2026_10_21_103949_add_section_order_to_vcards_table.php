<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The order of the parts of a business card below its header (quick buttons,
     * save button, contact details, map …), as the owner arranged them.
     * Empty means the standard order.
     */
    public function up(): void
    {
        Schema::table('vcards', function (Blueprint $table) {
            $table->json('section_order')->nullable()->after('accent_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vcards', function (Blueprint $table) {
            $table->dropColumn('section_order');
        });
    }
};
