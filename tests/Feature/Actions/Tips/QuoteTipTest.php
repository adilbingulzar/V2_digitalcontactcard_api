<?php

namespace Tests\Feature\Actions\Tips;

use App\Actions\Tips\QuoteTip;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\UserFee;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class QuoteTipTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int IDEAL = 10;

    private const int CREDIT_CARD = 706;

    public function test_recipient_pays_the_platform_fee_by_default_and_customer_pays_only_the_method_fee(): void
    {
        $this->platformFee('0.50', '0.00');
        $user = User::factory()->create();

        $quote = app(QuoteTip::class)->handle($user, 1000, self::IDEAL);

        $this->assertSame(5, $quote->recipientPlatformFeeCents());
        $this->assertSame(0, $quote->customerPlatformFeeCents());
        $this->assertSame(25, $quote->methodFeeCents);
        $this->assertSame(1025, $quote->totalCents());
    }

    public function test_customer_pays_the_platform_fee_on_top_when_the_user_chose_so(): void
    {
        $this->platformFee('0.50', '0.10');
        $user = User::factory()->create();
        UserSetting::factory()->for($user)->create(['key' => 'tax_deduction', 'value' => '1']);

        $quote = app(QuoteTip::class)->handle($user, 1000, self::CREDIT_CARD);

        // Platform fee: 0.5% of 10.00 + 0.10 = 0.15. Card fee: 2% of 10.15 + 0.35 = 0.55.
        $this->assertSame(15, $quote->customerPlatformFeeCents());
        $this->assertSame(0, $quote->recipientPlatformFeeCents());
        $this->assertSame(55, $quote->methodFeeCents);
        $this->assertSame('10.70', $quote->toArray()['total']);
    }

    public function test_an_admin_fee_override_replaces_the_platform_default(): void
    {
        $this->platformFee('0.50', '0.00');
        $user = User::factory()->create();
        UserFee::factory()->for($user)->create(['type' => 'tip_fees', 'percent_fee' => '2.00', 'fixed_fee' => '0.00']);

        $this->assertSame(20, app(QuoteTip::class)->handle($user, 1000, self::IDEAL)->platformFeeCents);
    }

    public function test_rejects_a_payment_method_that_is_not_offered_publicly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(QuoteTip::class)->handle(User::factory()->create(), 1000, 1927);
    }

    private function platformFee(string $percent, string $fixed): void
    {
        AppSetting::current()->update(['tip_fee_percent' => $percent, 'tip_fee_fixed' => $fixed]);
    }
}
