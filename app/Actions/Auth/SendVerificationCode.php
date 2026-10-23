<?php

namespace App\Actions\Auth;

use App\Jobs\SendSms;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Replaces any open email confirmation code of the user with a new one and emails it
 * (and texts it, when the user gave a phone number). Only a hash of the code is stored.
 */
class SendVerificationCode
{
    public function handle(User $user): void
    {
        $length = config('tiqs.auth.verification_code_length');
        $minutes = config('tiqs.auth.verification_code_minutes');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        VerificationCode::query()->whereBelongsTo($user)->where('purpose', 'confirm_email')->delete();

        $verificationCode = new VerificationCode;
        $verificationCode->forceFill([
            'user_id' => $user->id,
            'purpose' => 'confirm_email',
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($minutes),
        ])->save();

        Mail::to($user)->send(new VerificationCodeMail($user->name, $code, $minutes));

        if (filled($user->phone) && filled($user->country_code)) {
            SendSms::queueMessage((string) $user->country_code, (string) $user->phone, 'Your '.config('app.name').' verification code is '.$code.'. It is valid for '.$minutes.' minutes.', 'verification', $user->id);
        }
    }
}
