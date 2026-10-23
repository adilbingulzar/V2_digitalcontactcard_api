<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Auth\DeleteAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\DeleteAccountRequest;
use Illuminate\Http\Response;

class AccountController extends Controller
{
    public function destroy(DeleteAccountRequest $request, DeleteAccount $deleteAccount): Response
    {
        $deleteAccount->handle($request->user());

        return response()->noContent();
    }
}
