<?php

namespace App\Services\PayNl;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pay.nl Order API.
 *
 * @see https://developer.pay.nl/reference/api_create_order-1
 * @see https://developer.pay.nl/reference/api_get_status-1
 */
class PayNlClient
{
    /**
     * Start a payment. Not retried: a retry could create a second order.
     *
     * @param  array{amount_cents: int, currency: string, description: string, reference: string, return_url: string, exchange_url: string, payment_method_id: ?int, email: ?string, ip_address: ?string}  $order
     *
     * @throws PayNlException
     */
    public function createOrder(array $order): PayNlOrder
    {
        $payload = array_filter([
            'serviceId' => config('services.paynl.service_id'),
            'amount' => ['value' => $order['amount_cents'], 'currency' => $order['currency']],
            'description' => $order['description'],
            'reference' => $order['reference'],
            'returnUrl' => $order['return_url'],
            'exchangeUrl' => $order['exchange_url'],
            'paymentMethod' => $order['payment_method_id'] !== null ? ['id' => $order['payment_method_id']] : null,
            'customer' => array_filter([
                'email' => $order['email'],
                'ipAddress' => $order['ip_address'],
                'locale' => 'nl_NL',
            ]),
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        try {
            $response = $this->request()->post('/v1/orders', $payload)->throw();
        } catch (Throwable $exception) {
            throw new PayNlException('Pay.nl did not create the order: '.$this->reason($exception), previous: $exception);
        }

        $id = $response->json('id');

        if (! is_string($id) || $id === '') {
            throw new PayNlException('Pay.nl created an order without an id.');
        }

        return new PayNlOrder(
            id: $id,
            redirectUrl: $response->json('links.redirect'),
            statusCode: (int) $response->json('status.code', 20),
        );
    }

    /**
     * Current status code of an order. Safe to retry, so transient failures are retried.
     *
     * @throws PayNlException
     */
    public function orderStatusCode(string $orderId): int
    {
        try {
            $response = $this->request()
                ->retry([200, 600], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)))
                ->get('/v1/orders/'.rawurlencode($orderId).'/status')
                ->throw();
        } catch (Throwable $exception) {
            throw new PayNlException('Pay.nl did not return the order status: '.$this->reason($exception), previous: $exception);
        }

        $code = $response->json('status.code');

        if (! is_numeric($code)) {
            throw new PayNlException('Pay.nl returned an order status without a code.');
        }

        return (int) $code;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.paynl.base_url'))
            ->withBasicAuth((string) config('services.paynl.username'), (string) config('services.paynl.password'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(config('services.paynl.timeout'));
    }

    private function reason(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            return $exception->response->status().' '.($exception->response->json('detail') ?? $exception->response->json('title') ?? '');
        }

        return $exception->getMessage();
    }
}
