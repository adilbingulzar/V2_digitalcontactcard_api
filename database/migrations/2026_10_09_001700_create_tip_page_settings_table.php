<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Look and texts of a user's public tip/review page (legacy `tbl_settings_profile`).
     */
    public function up(): void
    {
        Schema::create('tip_page_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('profile_type')->default(0);
            $table->unsignedTinyInteger('default_page')->default(0)->comment('What the public page asks for: 0 = review only, 1 = tip only, 2 = tip and review');
            $table->text('page_header')->nullable();
            $table->text('page_title')->nullable();
            $table->text('success_header')->nullable();
            $table->text('success_message')->nullable();
            $table->unsignedTinyInteger('theme')->default(0);
            $table->string('background_image')->nullable();
            $table->string('panel_color', 150)->nullable()->comment('Background of the card (legacy back_color)');
            $table->string('page_color', 150)->nullable()->comment('Background of the whole page (legacy bg_color)');
            $table->string('text_color', 150)->nullable();
            $table->string('emoji_color', 150)->nullable();
            $table->unsignedTinyInteger('emoji_type')->default(0);
            $table->string('button_color', 150)->nullable();
            $table->string('font_size', 50)->nullable();
            $table->boolean('show_email_field')->default(true);
            $table->boolean('show_review_field')->default(true);
            $table->unsignedTinyInteger('review_style')->default(1);
            $table->boolean('pin_reviews')->default(false);
            $table->json('preset_amounts')->nullable()->comment('Suggested tip amounts, e.g. [3, 5, 10]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tip_page_settings');
    }
};
