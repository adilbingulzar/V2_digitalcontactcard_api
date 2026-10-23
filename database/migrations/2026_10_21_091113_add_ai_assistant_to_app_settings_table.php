<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The AI assistant of the card builder: which provider and model admins
     * chose, and the API key of each provider (encrypted with APP_KEY).
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(false)->after('terms_and_conditions');
            $table->string('ai_provider', 20)->default('anthropic')->after('ai_enabled')->comment('anthropic or openai');
            $table->text('anthropic_api_key')->nullable()->after('ai_provider')->comment('Encrypted');
            $table->string('anthropic_model', 100)->nullable()->after('anthropic_api_key');
            $table->text('openai_api_key')->nullable()->after('anthropic_model')->comment('Encrypted');
            $table->string('openai_model', 100)->nullable()->after('openai_api_key');
            $table->unsignedSmallInteger('ai_daily_limit')->default(30)->after('openai_model')->comment('Assistant messages per user per day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_enabled', 'ai_provider', 'anthropic_api_key', 'anthropic_model', 'openai_api_key', 'openai_model', 'ai_daily_limit']);
        });
    }
};
