<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProfileResource;
use App\Models\User;
use App\Models\VisitorLog;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * The public tip and review page behind /paytips/{slug} and /review/{slug}.
     */
    public function show(Request $request, User $user): PublicProfileResource
    {
        VisitorLog::create([
            'page_type' => $request->query('page') === 'review' ? 'review' : 'paytip',
            'page_url' => $request->headers->get('referer') ?? $request->fullUrl(),
            'ip_address' => $request->ip(),
            'completed' => false,
        ]);

        return new PublicProfileResource($user->load('tipPageSetting', 'paymentMethod'));
    }
}
