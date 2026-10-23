<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Custom colours of a user's vCard.
     */
    public function up(): void
    {
        Schema::create('vcard_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('primary_background', 30)->nullable();
            $table->string('secondary_background', 30)->nullable();
            $table->string('primary_text', 30)->nullable();
            $table->string('secondary_text', 30)->nullable();
            $table->string('primary_button', 30)->nullable();
            $table->string('secondary_button', 30)->nullable();
            $table->string('primary_button_text', 30)->nullable();
            $table->string('secondary_button_text', 30)->nullable();
            $table->string('border', 30)->nullable();
            $table->string('return_button', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vcard_themes');
    }
};
