<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\BuildSepaTransfer;
use App\Actions\Admin\MarkPayoutsPaid;
use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payout requests (the old "Payout", "Bank payout" and "Payout fees" pages):
 * export them for the bank, then mark them as paid.
 */
class PayoutController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(WithdrawalStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $validated['status'] ?? WithdrawalStatus::Requested->value;

        $query = Withdrawal::query()
            ->where('status', $status)
            ->when($validated['q'] ?? null, fn (Builder $withdrawals, string $q) => $withdrawals->where(fn (Builder $match) => $match
                ->where('reference', 'like', '%'.$q.'%')
                ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'))));

        $totals = (clone $query)->selectRaw('COALESCE(SUM(amount), 0) as amount, COALESCE(SUM(fee), 0) as fee')->first();
        $page = $query->with('user:id,name,email', 'user.bankAccount')->orderBy($status === 'paid' ? 'paid_at' : 'requested_at', $status === 'paid' ? 'desc' : 'asc')->paginate(50)->withQueryString();

        return $this->paginated($page, fn (Withdrawal $withdrawal): array => [
            'id' => $withdrawal->id,
            'reference' => $withdrawal->reference,
            'user' => ['id' => $withdrawal->user_id, 'name' => $withdrawal->user?->name, 'email' => $withdrawal->user?->email],
            'bank_account' => $withdrawal->user?->bankAccount === null ? null : [
                'account_name' => $withdrawal->user->bankAccount->account_name,
                'account_number' => $withdrawal->user->bankAccount->maskedAccountNumber(),
            ],
            'amount' => $withdrawal->amount,
            'fee' => $withdrawal->fee,
            'status' => $withdrawal->status,
            'requested_at' => $withdrawal->requested_at?->toIso8601String(),
            'paid_at' => $withdrawal->paid_at?->toIso8601String(),
        ], ['totals' => ['amount' => $this->money($totals->amount), 'fee' => $this->money($totals->fee)]]);
    }

    public function markPaid(Request $request, MarkPayoutsPaid $markPaid): JsonResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer', 'distinct']]);

        return response()->json(['data' => ['marked' => $markPaid->handle($validated['ids'])]]);
    }

    /**
     * The selected open payouts as a SEPA transfer file for the bank, or as CSV.
     */
    public function export(Request $request, BuildSepaTransfer $sepa): Response
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer', 'distinct'],
            'format' => ['required', 'in:sepa,csv'],
        ]);

        $withdrawals = Withdrawal::query()
            ->whereKey($validated['ids'])
            ->where('status', WithdrawalStatus::Requested)
            ->with('user.bankAccount')
            ->orderBy('id')
            ->get();
        abort_if($withdrawals->isEmpty(), 422, 'Select open payouts to export.');
        $name = 'payouts-'.now()->format('Y-m-d-His');

        if ($validated['format'] === 'sepa') {
            return response($sepa->handle($withdrawals), 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$name.'.xml"',
            ]);
        }

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Reference', 'Name', 'Email', 'Account holder', 'IBAN', 'BIC', 'Amount', 'Fee', 'Requested']);
        foreach ($withdrawals as $withdrawal) {
            $account = $withdrawal->user?->bankAccount;
            fputcsv($out, [
                $withdrawal->reference,
                $withdrawal->user?->name,
                $withdrawal->user?->email,
                $account?->account_name,
                $account?->account_number,
                $account?->swift_code,
                $withdrawal->amount,
                $withdrawal->fee,
                $withdrawal->requested_at?->toDateTimeString(),
            ]);
        }
        rewind($out);

        return response((string) stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'.csv"',
        ]);
    }
}
