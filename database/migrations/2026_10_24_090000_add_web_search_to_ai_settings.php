<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lets the card assistant search the internet through the provider's own
     * web search, and logs how many searches each request made, because
     * providers charge per search on top of the tokens.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('ai_web_search')->default(true)->after('ai_daily_limit');
            $table->decimal('anthropic_search_price', 10, 4)->nullable()->default(10)->after('anthropic_output_price');
            $table->decimal('openai_search_price', 10, 4)->nullable()->default(10)->after('openai_output_price');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->unsignedInteger('web_searches')->default(0)->after('output_tokens');
            $table->decimal('search_price', 10, 4)->nullable()->after('output_price')->comment('USD per 1000 web searches at the time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn(['web_searches', 'search_price']);
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_web_search', 'anthropic_search_price', 'openai_search_price']);
        });
    }
};
