<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\ImportAccounts;
use App\Models\Vcard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Bulk sign-up from a CSV file (the old "Auto signup").
 */
class AccountImportController extends AdminController
{
    public function store(Request $request, ImportAccounts $import): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            // As in the old form: "Business" means the accounts join a chosen business.
            'account_type' => ['sometimes', 'in:private,business'],
            'business_id' => ['required_if:account_type,business', 'nullable', 'integer', 'exists:businesses,id'],
            'message' => ['nullable', 'string', 'max:1000'],
            'country_code' => ['required', 'regex:/^\+?\d{1,4}$/'],
            'country' => ['required', 'string', 'size:2'],
            'theme' => ['required', 'integer', Rule::in([Vcard::THEME_GREEN, Vcard::THEME_CUSTOM])],
        ]);

        $result = $import->handle($request->file('file'), [
            'business_id' => $validated['business_id'] ?? null,
            'country_code' => $validated['country_code'],
            'country' => strtoupper($validated['country']),
            'theme' => (int) $validated['theme'],
            'message' => $validated['message'] ?? null,
        ]);

        return response()->json(['data' => $result]);
    }

    /**
     * An empty CSV file with the expected columns.
     */
    public function template(): Response
    {
        return response(implode(',', ImportAccounts::COLUMNS)."\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="accounts-template.csv"',
        ]);
    }
}
