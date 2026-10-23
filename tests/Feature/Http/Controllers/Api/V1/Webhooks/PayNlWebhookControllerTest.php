<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Webhooks;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tip;
use App\Models\UserNotification;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayNlWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_forged_paid_notification_does_not_mark_a_pending_payment_paid(): void
    {
        Http::preventStrayRequests();
        Http::fake(['connect.pay.nl/v1/orders/order-1/status' => $this->payNlStatus(50)]);
        $payment = $this->pendingTipPayment('order-1');

        $this->postJson(route('api.v1.webhooks.paynl'), [
            'event' => 'status_changed',
            'object' => ['id' => 'order-1', 'status' => ['code' => 100, 'action' => 'PAID']],
        ])->assertOk()->assertJsonPath('result', true);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $payment->tips()->sole()->payment_status);
    }

    public function test_marks_payment_and_tips_paid_when_pay_nl_confirms_and_notifies_once(): void
    {
        Http::preventStrayRequests();
        Http::fake(['connect.pay.nl/v1/orders/order-1/status' => $this->payNlStatus(100)]);
        $payment = $this->pendingTipPayment('order-1');
        $tip = $payment->tips()->sole();

        $this->postJson(route('api.v1.webhooks.paynl'), ['object' => ['id' => 'order-1']])->assertOk();
        $this->postJson(route('api.v1.webhooks.paynl'), ['object' => ['id' => 'order-1']])->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(PaymentStatus::Paid, $tip->refresh()->payment_status);
        $this->assertSame(1, UserNotification::where('user_id', $tip->recipient_id)->where('type', 'service_tip_self')->count());
    }

    public function test_accepts_the_legacy_exchange_format(): void
    {
        Http::fake(['connect.pay.nl/v1/orders/order-1/status' => $this->payNlStatus(-90)]);
        $payment = $this->pendingTipPayment('order-1');

        $this->postJson(route('api.v1.webhooks.paynl'), ['action' => 'cancel', 'order_id' => 'order-1'])->assertOk();

        $this->assertSame(PaymentStatus::Cancelled, $payment->refresh()->status);
    }

    public function test_unknown_order_is_acknowledged_without_calling_pay_nl(): void
    {
        Http::preventStrayRequests();

        $this->postJson(route('api.v1.webhooks.paynl'), ['object' => ['id' => 'unknown']])
            ->assertOk()
            ->assertJsonPath('result', true);
    }

    public function test_returns_503_so_pay_nl_retries_when_the_status_cannot_be_checked(): void
    {
        Http::fake(['connect.pay.nl/v1/orders/order-1/status' => Http::response(null, 500)]);
        $payment = $this->pendingTipPayment('order-1');

        $this->postJson(route('api.v1.webhooks.paynl'), ['object' => ['id' => 'order-1']])
            ->assertStatus(503)
            ->assertJsonPath('result', false);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    private function pendingTipPayment(string $orderId): Payment
    {
        $payment = Payment::factory()->create(['pay_order_id' => $orderId]);
        $tip = Tip::factory()->create(['recipient_id' => $payment->recipient_id]);
        $tip->forceFill(['payment_id' => $payment->id])->save();

        return $payment;
    }

    private function payNlStatus(int $code): PromiseInterface
    {
        return Http::response(['id' => 'order-1', 'status' => ['code' => $code, 'name' => 'x', 'action' => 'x']]);
    }
}
