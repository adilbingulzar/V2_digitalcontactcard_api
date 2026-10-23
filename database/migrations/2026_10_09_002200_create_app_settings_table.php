<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Platform-wide settings managed by admins. Holds a single row.
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('tip_fee_fixed', 10, 2)->default(0);
            $table->decimal('tip_fee_percent', 5, 2)->default(0.5);
            $table->boolean('business_subscription_required')->default(false);
            $table->boolean('user_subscription_required')->default(false);
            $table->decimal('user_plan_monthly', 10, 2)->default(5.55);
            $table->decimal('user_plan_yearly', 10, 2)->default(55.55);
            $table->decimal('business_plan_monthly', 10, 2)->default(9.95);
            $table->decimal('business_plan_yearly', 10, 2)->default(99.95);
            $table->unsignedSmallInteger('free_trial_days')->default(30)->comment('New accounts can request payouts without a plan for this many days');
            $table->string('payout_debtor_name')->nullable()->comment('Account holder payouts are paid from (SEPA export)');
            $table->text('payout_debtor_iban')->nullable()->comment('Encrypted');
            $table->string('payout_debtor_bic', 11)->nullable();
            $table->string('support_url')->nullable();
            $table->string('support_email')->nullable();
            $table->longText('terms_and_conditions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
