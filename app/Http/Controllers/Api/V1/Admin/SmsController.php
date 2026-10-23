<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Jobs\SendSms;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Text messages: the report of everything sent (the old "SMS report") and
 * writing a message to one number or to users (the old "Compose SMS").
 */
class SmsController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purpose' => ['nullable', 'in:verification,password_reset,admin'],
            'status' => ['nullable', 'in:queued,sent,logged,failed'],
        ]);

        $page = SmsMessage::query()
            ->when($validated['purpose'] ?? null, fn (Builder $messages, string $purpose) => $messages->where('purpose', $purpose))
            ->when($validated['status'] ?? null, fn (Builder $messages, string $status) => $messages->where('status', $status))
            ->with('user:id,name', 'sender:id,name')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return $this->paginated($page, fn (SmsMessage $sms): array => [
            'id' => $sms->id,
            'to' => '+'.$sms->country_code.' '.$sms->phone,
            'user' => $sms->user?->name,
            'sent_by' => $sms->sender?->name,
            'purpose' => $sms->purpose,
            'message' => $sms->message,
            'status' => $sms->status,
            'error' => $sms->error,
            'created_at' => $sms->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Send to one number, or to every user with a phone number (optionally
     * only those who received tips, as the old "tips users" option did).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['required', 'in:number,numbers,all_users,tipped_users'],
            'country_code' => ['required_if:audience,number,numbers', 'nullable', 'regex:/^\+?\d{1,4}$/'],
            'phone' => ['required_if:audience,number', 'nullable', 'regex:/^[\d\s]{6,20}$/'],
            // "Sender number one by one with comma": several numbers with the same country code.
            'phones' => ['required_if:audience,numbers', 'nullable', 'string', 'max:3000'],
            'message' => ['required', 'string', 'max:480'],
            'unicode' => ['sometimes', 'boolean'],
        ]);
        $adminId = $request->user()->id;
        // With "Unicode SMS" off the text is reduced to plain characters, so it fits standard SMS.
        $message = $request->boolean('unicode', true) ? $validated['message'] : Str::ascii($validated['message']);

        if ($validated['audience'] === 'number') {
            SendSms::queueMessage($validated['country_code'], $validated['phone'], $message, 'admin', null, $adminId);

            return response()->json(['data' => ['queued' => 1]], 202);
        }

        if ($validated['audience'] === 'numbers') {
            $phones = collect(preg_split('/[,;\n]+/', (string) $validated['phones']))
                ->map(fn (string $phone): string => preg_replace('/[^\d]/', '', $phone) ?? '')
                ->filter()
                ->unique()
                ->values();
            $invalid = $phones->reject(fn (string $phone): bool => strlen($phone) >= 6 && strlen($phone) <= 20);
            if ($phones->isEmpty() || $invalid->isNotEmpty() || $phones->count() > 100) {
                throw ValidationException::withMessages(['phones' => $phones->count() > 100
                    ? 'Send to at most 100 numbers at a time.'
                    : 'Enter phone numbers of 6 to 20 digits, separated by commas.']);
            }
            $phones->each(fn (string $phone) => SendSms::queueMessage($validated['country_code'], $phone, $message, 'admin', null, $adminId));

            return response()->json(['data' => ['queued' => $phones->count()]], 202);
        }

        $queued = 0;
        User::query()
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->whereNotNull('country_code')->where('country_code', '!=', '')
            ->when($validated['audience'] === 'tipped_users', fn (Builder $users) => $users->whereHas('receivedTips', fn (Builder $tips) => $tips->paid()))
            ->select(['id', 'country_code', 'phone'])
            ->lazyById()
            ->each(function (User $user) use ($message, $adminId, &$queued): void {
                SendSms::queueMessage((string) $user->country_code, (string) $user->phone, $message, 'admin', $user->id, $adminId);
                $queued++;
            });

        return response()->json(['data' => ['queued' => $queued]], 202);
    }
}
