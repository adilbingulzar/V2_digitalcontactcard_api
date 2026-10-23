<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One table for tips, ratings and reviews. Replaces the legacy
     * `tbl_touchtip` (given to a person) and `tbl_tips_spot` (given at a spot):
     * a spot tip has `spot_id` set.
     */
    public function up(): void
    {
        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->comment('online = paid tip, rating = review or rating');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('spot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('payment_status', 20)->default('pending');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pay_order_id', 40)->nullable()->index()->comment('Pay.nl order id of tips imported from the old app; new tips use payment_id');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('withdrawal_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('rating')->nullable()->comment('1 to 5 stars');
            $table->text('review')->nullable();
            $table->boolean('is_review_pinned')->default(false)->comment('Legacy sticky_review: shown first on review widgets');
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_email')->nullable();

            $table->string('token', 64)->unique()->comment('Random key that ties a checkout to this row');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'payment_status', 'withdrawal_id']);
            $table->index(['business_id', 'spot_id', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tips');
    }
};
