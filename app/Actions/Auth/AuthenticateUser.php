<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Checks an email and password. Accounts imported from the old app still have
 * an unsalted md5 hash; on their first successful login it is replaced by a
 * modern hash and removed.
 *
 * Support staff can sign in to any account, admin accounts included, with
 * the master password (tiqs.auth.master_password), as in the old app. It is
 * off while none is set, never replaces the account's own password and every
 * use is logged.
 *
 * A few people from the old app have two accounts on one email address. As in
 * the old app, the oldest account whose password matches is signed in.
 */
class AuthenticateUser
{
    public function handle(string $email, string $password): ?User
    {
        $candidates = User::where('email', Str::lower(trim($email)))->orderBy('id')->get();

        if ($candidates->isEmpty()) {
            // Spend the same time as a real check, so response time does not reveal which emails exist.
            Hash::check($password, Hash::make('timing-equaliser'));

            return null;
        }

        $user = $candidates->first(fn (User $candidate): bool => $candidate->passwordMatches($password));

        if ($user === null) {
            return $this->signInWithMasterPassword($candidates->first(), $password);
        }

        if ($user->password === null) {
            $user->forceFill(['password' => $password, 'legacy_password_md5' => null])->save();
        }

        return $user;
    }

    private function signInWithMasterPassword(User $user, string $password): ?User
    {
        $masterPassword = (string) config('tiqs.auth.master_password');

        if ($masterPassword === '' || ! hash_equals($masterPassword, $password)) {
            return null;
        }

        Log::warning('Signed in with the master password.', ['user_id' => $user->id, 'is_admin' => $user->isAdmin(), 'ip' => request()->ip()]);

        return $user;
    }
}
