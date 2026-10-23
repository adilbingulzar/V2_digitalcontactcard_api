<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateBankAccountRequest;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    /**
     * The bank account payouts go to. The account number is only shown masked.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => self::present($request->user()->bankAccount)]);
    }

    public function update(UpdateBankAccountRequest $request): JsonResponse
    {
        $account = $request->user()->bankAccount()->updateOrCreate([], [
            'bank_name' => $request->validated('bank_name'),
            'account_name' => $request->validated('account_name'),
            'account_type' => $request->validated('account_type'),
            'account_number' => $request->input('account_number'),
            'swift_code' => $request->input('swift_code'),
        ]);

        return response()->json(['data' => self::present($account)]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function present(?BankAccount $account): ?array
    {
        return $account === null ? null : [
            'bank_name' => $account->bank_name,
            'account_name' => $account->account_name,
            'account_type' => $account->account_type,
            'account_number' => $account->maskedAccountNumber(),
            'swift_code' => $account->swift_code,
        ];
    }
}
