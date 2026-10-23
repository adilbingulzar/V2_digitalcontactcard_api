<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The chat between a user and the AI assistant of the card builder, so the
     * user can come back to it later. Kept as plain text, independent of the provider.
     */
    public function up(): void
    {
        Schema::create('vcard_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->comment('user or assistant');
            $table->text('content');
            $table->json('changes')->nullable()->comment('Card fields the assistant changed in its answer');
            $table->string('provider', 20)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vcard_assistant_messages');
    }
};
