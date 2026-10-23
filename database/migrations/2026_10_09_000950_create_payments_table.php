<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One checkout at Pay.nl. A tip that is split between several team members
     * is one payment with one tip row per member.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Public reference, used in the return URL');
            $table->string('purpose', 20)->default('tip')->comment('tip or subscription');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete()->comment('Person the customer tipped (the business owner for a spot tip); the money may be shared with a team');
            $table->foreignId('spot_id')->nullable()->constrained()->nullOnDelete()->comment('Set when the tip was given at a spot');
            $table->string('pay_order_id', 64)->nullable()->unique()->comment('Order id at Pay.nl');
            $table->unsignedInteger('pay_method_id')->nullable()->comment('Pay.nl payment method id');
            $table->string('status', 20)->default('pending');
            $table->smallInteger('pay_status_code')->nullable()->comment('Last status code reported by Pay.nl');
            $table->decimal('tip_amount', 10, 2);
            $table->decimal('platform_fee', 10, 2)->default(0)->comment('Paid by the customer on top of the tip');
            $table->decimal('method_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->comment('Charged to the customer');
            $table->char('currency', 3)->default('EUR');
            $table->string('payer_email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
