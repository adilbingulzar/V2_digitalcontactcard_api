<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The card builder's work in progress: everything the user (or the AI
     * assistant) changed and did not publish yet, saved as they go, so a
     * refresh or another device shows the same. Removed on publish.
     */
    public function up(): void
    {
        Schema::create('vcard_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vcard_drafts');
    }
};
