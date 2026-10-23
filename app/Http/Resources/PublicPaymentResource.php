<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Status of a checkout, for the page the customer returns to from Pay.nl.
 *
 * @mixin Payment
 */
class PublicPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $recipient = $this->recipient;

        if ($this->purpose === 'subscription') {
            return [
                'id' => $this->uuid,
                'purpose' => 'subscription',
                'status' => $this->status,
                'is_final' => $this->status->isFinal(),
                'tip_amount' => $this->tip_amount,
                'total_amount' => $this->total_amount,
                'currency' => $this->currency,
                'spot' => null,
                'recipient' => null,
            ];
        }

        return [
            'id' => $this->uuid,
            'purpose' => 'tip',
            'status' => $this->status,
            'is_final' => $this->status->isFinal(),
            'tip_amount' => $this->tip_amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'spot' => $this->spot === null ? null : [
                'name' => $this->spot->name,
                'slug' => $this->spot->slug,
                'business_id' => $this->spot->business_id,
                'business_name' => $this->spot->business->name,
            ],
            'recipient' => $recipient === null || $this->spot !== null ? null : [
                'name' => $recipient->name,
                'slug' => $recipient->slug,
                'success_header' => $recipient->tipPageSetting?->success_header,
                'success_message' => $recipient->tipPageSetting?->success_message,
            ],
        ];
    }
}
