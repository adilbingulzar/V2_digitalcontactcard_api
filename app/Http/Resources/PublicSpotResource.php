<?php

namespace App\Http\Resources;

use App\Actions\Tips\SplitTip;
use App\Models\Spot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a customer sees on a spot's tip and review page (/paytips/spot/{business}/{spot}).
 * The page uses the business owner's tip page settings, as in the old app.
 *
 * @mixin Spot
 */
class PublicSpotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->business->owner;
        $ratings = $this->tips()->countsAsFeedback()->whereNotNull('rating');

        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'business' => [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'avatar' => $owner?->avatarUrl(),
            ],
            'can_receive_tips' => app(SplitTip::class)->canReceiveSpotTips($this->resource),
            'page' => PublicProfileResource::page($owner?->tipPageSetting),
            'tips' => PublicProfileResource::tipOptions($owner),
            'rating' => [
                'average' => round((float) (clone $ratings)->avg('rating'), 1),
                'count' => (clone $ratings)->count(),
            ],
        ];
    }
}
