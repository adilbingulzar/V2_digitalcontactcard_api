<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\FeeCollection;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TipCheckoutControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paynl.base_url' => 'https://connect.pay.nl',
            'services.paynl.username' => 'AT-0000-0000',
            'services.paynl.password' => 'token',
            'services.paynl.service_id' => 'SL-0000-0000',
        ]);
    }

    public function test_starts_a_pay_nl_order_and_returns_its_payment_page(): void
    {
        Http::preventStrayRequests();
        Http::fake(['connect.pay.nl/v1/orders' => Http::response([
            'id' => 'order-123',
            'status' => ['code' => 20, 'action' => 'PENDING'],
            'links' => ['redirect' => 'https://checkout.pay.nl/order-123'],
        ], 201)]);
        AppSetting::current()->update(['tip_fee_percent' => '0.50', 'tip_fee_fixed' => '0.00']);
        $user = User::factory()->create(['slug' => 'anna']);

        $response = $this->postJson(route('api.v1.public.profiles.checkout.store', 'anna'), [
            'amount' => '10.00', 'payment_method_id' => 10, 'email' => 'guest@example.com', 'rating' => 5, 'review' => 'Great service',
        ]);

        $response->assertCreated()->assertJsonPath('redirect_url', 'https://checkout.pay.nl/order-123');
        $payment = Payment::sole();
        $this->assertSame('order-123', $payment->pay_order_id);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('10.25', $payment->total_amount);
        $tip = $payment->tips()->sole();
        $this->assertSame($user->id, $tip->recipient_id);
        $this->assertSame('9.95', $tip->amount);
        $this->assertSame(5, $tip->rating);
        $this->assertSame(PaymentStatus::Pending, $tip->payment_status);
        $this->assertSame('0.05', FeeCollection::sole()->fee_amount);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://connect.pay.nl/v1/orders'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('AT-0000-0000:token'))
            && $request['amount'] === ['value' => 1025, 'currency' => 'EUR']
            && $request['serviceId'] === 'SL-0000-0000'
            && $request['reference'] === $payment->uuid
            && $request['paymentMethod'] === ['id' => 10]
            && $request['exchangeUrl'] === route('api.v1.webhooks.paynl')
            && $request['returnUrl'] === config('tiqs.frontend_url').'/payment/'.$payment->uuid);
    }

    public function test_returns_502_and_marks_the_payment_failed_when_pay_nl_refuses(): void
    {
        Http::preventStrayRequests();
        Http::fake(['connect.pay.nl/v1/orders' => Http::response(['code' => 'PAY-2002', 'title' => 'Amount too low'], 422)]);
        User::factory()->create(['slug' => 'anna']);

        $this->postJson(route('api.v1.public.profiles.checkout.store', 'anna'), ['amount' => '5.00', 'payment_method_id' => 10])
            ->assertStatus(502)
            ->assertJsonPath('message', 'The payment could not be started. Please try again.');

        $payment = Payment::sole();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame(PaymentStatus::Failed, $payment->tips()->sole()->payment_status);
    }

    public function test_returns_422_for_a_user_who_cannot_receive_tips(): void
    {
        Http::preventStrayRequests();
        User::factory()->unverified()->create(['slug' => 'pending']);

        $this->postJson(route('api.v1.public.profiles.checkout.store', 'pending'), ['amount' => '5.00', 'payment_method_id' => 10])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This person cannot receive tips right now.');

        $this->assertSame(0, Payment::count());
    }

    public function test_rejects_a_payment_method_the_user_does_not_offer(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);
        PaymentMethod::factory()->for($user)->create(['pay_option_ids' => [10]]);

        $this->postJson(route('api.v1.public.profiles.checkout.store', 'anna'), ['amount' => '5.00', 'payment_method_id' => 706])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method_id' => 'This payment method is not available.']);
    }

    public function test_rejects_an_amount_below_the_minimum(): void
    {
        User::factory()->create(['slug' => 'anna']);

        $this->postJson(route('api.v1.public.profiles.checkout.store', 'anna'), ['amount' => '0.10', 'payment_method_id' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_returns_404_for_an_unknown_profile(): void
    {
        $this->postJson(route('api.v1.public.profiles.checkout.store', 'nobody'), ['amount' => '5.00', 'payment_method_id' => 10])
            ->assertNotFound();
    }
}
