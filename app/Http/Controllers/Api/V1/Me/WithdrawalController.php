<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WithdrawalController extends Controller
{
    /**
     * The signed-in user's payout requests (the old "Statement" page), newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return WithdrawalResource::collection(
            Withdrawal::query()->whereBelongsTo($request->user())->latest('id')->paginate(25),
        );
    }
}
