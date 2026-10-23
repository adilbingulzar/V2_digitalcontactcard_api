<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * What each provider charges for the chosen model, in USD per million
     * tokens, so the cost of every AI request can be logged.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->decimal('anthropic_input_price', 10, 4)->nullable()->after('anthropic_model');
            $table->decimal('anthropic_output_price', 10, 4)->nullable()->after('anthropic_input_price');
            $table->decimal('openai_input_price', 10, 4)->nullable()->after('openai_model');
            $table->decimal('openai_output_price', 10, 4)->nullable()->after('openai_input_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['anthropic_input_price', 'anthropic_output_price', 'openai_input_price', 'openai_output_price']);
        });
    }
};
