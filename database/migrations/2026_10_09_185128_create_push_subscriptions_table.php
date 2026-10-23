<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Browsers that allowed push notifications (Web Push). One user can have several.
     */
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint')->comment('Push service URL of the browser');
            $table->char('endpoint_hash', 64)->unique()->comment('SHA-256 of the endpoint, for lookups');
            $table->string('public_key', 120)->comment('Browser key (p256dh), base64url');
            $table->string('auth_token', 60)->comment('Browser auth secret, base64url');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
