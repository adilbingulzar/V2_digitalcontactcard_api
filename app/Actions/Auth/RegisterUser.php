<?php

namespace App\Actions\Auth;

use App\Actions\Notifications\NotifyUser;
use App\Enums\AccountType;
use App\Enums\EmployeeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a new account the way the old sign-up did: a business account also
 * gets its business, and signing up through someone's referral link creates
 * the referral reward and, when the referrer is a business, a join request.
 *
 * New accounts are never admins; admins are created deliberately.
 */
class RegisterUser
{
    /**
     * @param  array{first_name: string, last_name: string, email: string, password: string, account_type: string, business_name?: string|null, phone: string, country_code: string, country: string, referral?: string|null}  $input
     */
    public function handle(array $input): User
    {
        $user = DB::transaction(function () use ($input): User {
            $user = new User([
                'name' => trim($input['first_name'].' '.$input['last_name']),
                'email' => Str::lower($input['email']),
                'password' => $input['password'],
                'account_type' => $input['account_type'],
                'phone' => $input['phone'],
                'country_code' => ltrim($input['country_code'], '+0'),
                'country' => $input['country'],
                'slug' => 'pending-'.Str::random(20),
            ]);
            $user->forceFill(['role' => UserRole::User, 'status' => UserStatus::Pending])->save();

            $user->forceFill(['slug' => self::slugFor($input['first_name'], $user->id)])->save();

            if ($user->account_type === AccountType::Business) {
                $this->createBusiness($user, $input['business_name'] ?? $user->name);
            }

            $this->welcome($user);

            if (! empty($input['referral'])) {
                $this->applyReferral($user, $input['referral']);
            }

            return $user;
        });

        event(new Registered($user));

        return $user;
    }

    /**
     * Public URL name: the first name in lower case letters and digits, plus the user id
     * (the old app's format, e.g. "anna42").
     */
    public static function slugFor(string $firstName, int $userId): string
    {
        $base = Str::of($firstName)->ascii()->lower()->replaceMatches('/[^a-z0-9]/', '')->limit(30, '')->toString();

        return ($base === '' ? 'user' : $base).$userId;
    }

    private function createBusiness(User $owner, string $name): Business
    {
        $business = new Business(['name' => $name, 'email' => $owner->email]);
        $business->forceFill([
            'owner_id' => $owner->id,
            'code' => $this->uniqueBusinessCode(),
            'is_active' => true,
        ])->save();

        UserNotification::create([
            'user_id' => $owner->id,
            'business_id' => $business->id,
            'audience' => 'business',
            'type' => 'welcome',
            'title' => 'Hi! '.$business->name,
            'message' => 'Welcome to '.config('app.name').'. Add your team and spots to start receiving tips.',
            'icon' => 'fa-handshake',
        ]);

        return $business;
    }

    private function uniqueBusinessCode(): string
    {
        do {
            $code = 'BIZ'.str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
        } while (Business::where('code', $code)->exists());

        return $code;
    }

    private function welcome(User $user): void
    {
        UserNotification::create([
            'user_id' => $user->id,
            'audience' => 'user',
            'type' => 'welcome',
            'title' => 'Hi! '.Str::before($user->name, ' '),
            'message' => 'Welcome to '.config('app.name').'. It makes it easy to receive tips and manage your profile.',
            'icon' => 'fa-handshake',
        ]);
    }

    /**
     * The referral link carries the referrer's public URL name.
     */
    private function applyReferral(User $user, string $referrerSlug): void
    {
        $referrer = User::where('slug', $referrerSlug)->first();

        if ($referrer === null || $referrer->is($user)) {
            return;
        }

        $isFirstReferral = ! Referral::where('referrer_id', $referrer->id)->exists();
        $referral = new Referral;
        $referral->forceFill([
            'referrer_id' => $referrer->id,
            'referred_user_id' => $user->id,
            'amount' => config($isFirstReferral ? 'tiqs.referral_reward.first' : 'tiqs.referral_reward.next'),
            'is_confirmed' => false,
        ])->save();

        $business = $referrer->business;
        if ($business === null) {
            return;
        }

        $employee = new Employee([
            'user_id' => $user->id,
            'agreement_id' => $business->default_agreement_id,
            'requested_by' => 'business',
        ]);
        $employee->business()->associate($business);
        $employee->forceFill(['status' => EmployeeStatus::Pending])->save();

        app(NotifyUser::class)->handle($user->id, 'join_request', [
            'business_id' => $business->id,
            'audience' => 'user',
            'title' => $business->name,
            'message' => $business->name.' sent you a request to join their team.',
            'icon' => 'fa-briefcase',
        ]);
    }
}
