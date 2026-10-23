<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every request to an AI provider, with the tokens it used and what it cost,
     * so users can be billed for it later. Never removed with the chat itself.
     */
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 30)->comment('card_assistant, card_import or connection_test');
            $table->string('provider', 20);
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->decimal('input_price', 10, 4)->nullable()->comment('USD per million input tokens at the time');
            $table->decimal('output_price', 10, 4)->nullable()->comment('USD per million output tokens at the time');
            $table->decimal('cost', 12, 6)->nullable()->comment('USD; empty while no prices were set');
            $table->boolean('succeeded')->default(true)->comment('False when the answer could not be used; the tokens were still charged');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
