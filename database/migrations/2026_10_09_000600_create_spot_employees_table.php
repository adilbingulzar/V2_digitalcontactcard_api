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
        Schema::create('spot_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_role_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_live')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['spot_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spot_employees');
    }
};
