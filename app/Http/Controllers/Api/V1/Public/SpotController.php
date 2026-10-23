<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSpotResource;
use App\Models\Business;
use App\Models\Spot;
use App\Models\VisitorLog;
use Illuminate\Http\Request;

class SpotController extends Controller
{
    /**
     * The public tip and review page behind /paytips/spot/{business}/{spot} (printed QR codes).
     */
    public function show(Request $request, Business $business, Spot $spot): PublicSpotResource
    {
        VisitorLog::create([
            'page_type' => $request->query('page') === 'review' ? 'review_spot' : 'paytip_spot',
            'page_url' => $request->headers->get('referer') ?? $request->fullUrl(),
            'ip_address' => $request->ip(),
            'completed' => false,
        ]);

        return new PublicSpotResource($spot->load('business.owner.tipPageSetting', 'business.owner.paymentMethod'));
    }
}
