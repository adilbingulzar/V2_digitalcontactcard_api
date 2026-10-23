<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The name and kind of a file the user attached to a message (a picture or
     * PDF of a card to copy). The file itself is only read for that one answer.
     */
    public function up(): void
    {
        Schema::table('vcard_assistant_messages', function (Blueprint $table) {
            $table->json('attachment')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vcard_assistant_messages', function (Blueprint $table) {
            $table->dropColumn('attachment');
        });
    }
};
