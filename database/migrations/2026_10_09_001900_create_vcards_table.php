<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Digital business card shown at /vcards/{user slug}.
     */
    public function up(): void
    {
        Schema::create('vcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name')->nullable();
            $table->string('business_title')->nullable();
            $table->string('business_email')->nullable();
            $table->text('business_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->unsignedTinyInteger('theme')->default(1);
            $table->json('social_links')->nullable();
            $table->text('google_review_url')->nullable();
            $table->text('ambassador_url')->nullable();
            $table->text('appointment_url')->nullable();
            $table->text('google_wallet_url')->nullable();
            $table->text('apple_wallet_url')->nullable();
            $table->boolean('show_business_name')->default(true);
            $table->boolean('show_location')->default(true);
            $table->boolean('show_opening_hours')->default(true);
            $table->boolean('show_review')->default(true);
            $table->boolean('show_business_icon')->default(true);
            $table->boolean('show_email_icon')->default(true);
            $table->boolean('show_phone_icon')->default(true);
            $table->boolean('show_address_icon')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vcards');
    }
};
