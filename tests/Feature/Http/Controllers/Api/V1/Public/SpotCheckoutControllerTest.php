<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Spot;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpotCheckoutControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_starts_a_spot_tip_that_goes_to_the_owner_when_nobody_is_on_shift(): void
    {
        Http::preventStrayRequests();
        Http::fake(['connect.pay.nl/v1/orders' => Http::response(['id' => 'order-7', 'links' => ['redirect' => 'https://pay.nl/x']], 201)]);
        AppSetting::current()->update(['tip_fee_percent' => '0.00', 'tip_fee_fixed' => '0.00']);
        $spot = Spot::factory()->create(['slug' => 'terrace']);

        $this->postJson(route('api.v1.public.spots.checkout.store', [$spot->business_id, 'terrace']), [
            'amount' => '4.00', 'payment_method_id' => 10, 'rating' => 4,
        ])->assertCreated()->assertJsonPath('redirect_url', 'https://pay.nl/x');

        $payment = Payment::sole();
        $this->assertSame($spot->id, $payment->spot_id);
        $tip = $payment->tips()->sole();
        $this->assertSame($spot->business->owner_id, $tip->recipient_id);
        $this->assertSame($spot->id, $tip->spot_id);
        $this->assertSame('4.00', $tip->amount);
        $this->assertSame(4, $tip->rating);
        $this->assertSame(PaymentStatus::Pending, $tip->payment_status);
        Http::assertSent(fn (Request $request): bool => $request['amount']['value'] === 425);
    }

    public function test_returns_404_for_a_spot_of_another_business(): void
    {
        $spot = Spot::factory()->create(['slug' => 'terrace']);
        $otherBusiness = Business::factory()->create();

        $this->postJson(route('api.v1.public.spots.checkout.store', [$otherBusiness->id, 'terrace']), ['amount' => '4.00', 'payment_method_id' => 10])
            ->assertNotFound();

        $this->assertSame(0, Payment::count());
        $this->assertNotNull($spot->id);
    }

    public function test_returns_422_for_an_inactive_spot(): void
    {
        $spot = Spot::factory()->create(['slug' => 'closed', 'is_active' => false]);

        $this->postJson(route('api.v1.public.spots.checkout.store', [$spot->business_id, 'closed']), ['amount' => '4.00', 'payment_method_id' => 10])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tips cannot be given at this spot right now.');
    }
}
