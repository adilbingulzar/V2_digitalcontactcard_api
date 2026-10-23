<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_role_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requested_by', 20)->default('business')->comment('Who sent the join request: business or employee');
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable()->comment('Reason given when a request is declined');
            $table->boolean('is_live')->default(false)->comment('Currently on shift and visible for tips');
            $table->timestamp('last_live_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
