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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('code', 20)->unique()->comment('Public business code, e.g. BIZ12345678');
            $table->string('pay_vendor_id')->nullable()->comment('Pay.nl vendor/merchant id');
            // Constrained in the agreements migration, which runs after this one.
            $table->unsignedBigInteger('default_agreement_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
