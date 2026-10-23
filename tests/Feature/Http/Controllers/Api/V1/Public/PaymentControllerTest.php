<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Models\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_checks_a_pending_payment_with_pay_nl_before_answering(): void
    {
        Http::preventStrayRequests();
        Http::fake(['connect.pay.nl/v1/orders/order-9/status' => Http::response(['status' => ['code' => 100]])]);
        $payment = Payment::factory()->create(['pay_order_id' => 'order-9']);

        $this->getJson(route('api.v1.public.payments.show', $payment))
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.is_final', true)
            ->assertJsonPath('data.recipient.slug', $payment->recipient->slug);
    }

    public function test_does_not_call_pay_nl_for_a_final_payment(): void
    {
        Http::preventStrayRequests();
        $payment = Payment::factory()->paid()->create();

        $this->getJson(route('api.v1.public.payments.show', $payment))->assertOk()->assertJsonPath('data.status', 'paid');
    }

    public function test_still_answers_with_the_known_status_when_pay_nl_is_unreachable(): void
    {
        Http::fake(['connect.pay.nl/*' => Http::response(null, 503)]);
        $payment = Payment::factory()->create(['pay_order_id' => 'order-9']);

        $this->getJson(route('api.v1.public.payments.show', $payment))->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_returns_404_for_an_unknown_payment(): void
    {
        $this->getJson(route('api.v1.public.payments.show', '00000000-0000-0000-0000-000000000000'))->assertNotFound();
    }
}
