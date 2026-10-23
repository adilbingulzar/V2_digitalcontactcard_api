<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every text message the app sends, for the admin SMS report.
     */
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Recipient, when it is a user');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete()->comment('Admin who wrote it; empty for automatic messages');
            $table->string('purpose', 30)->comment('verification, password_reset or admin');
            $table->string('country_code', 6);
            $table->string('phone', 30);
            $table->text('message');
            $table->string('status', 20)->default('queued')->comment('queued, sent, logged (no gateway) or failed');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['purpose', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
