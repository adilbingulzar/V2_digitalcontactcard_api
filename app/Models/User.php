<?php

namespace App\Models;

use App\Actions\Auth\SendVerificationCode;
use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendSms;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'slug', 'account_type', 'phone', 'country_code', 'country',
    'birth_date', 'address', 'bio', 'avatar', 'accepts_individual_tips', 'push_token',
])]
#[Hidden(['password', 'legacy_password_md5', 'remember_token', 'push_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Users confirm their email by entering a code instead of clicking a signed link.
     */
    public function sendEmailVerificationNotification(): void
    {
        app(SendVerificationCode::class)->handle($this);
    }

    /**
     * The reset link goes by email and, when the user gave a phone number, also by text message.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));

        if (filled($this->phone) && filled($this->country_code)) {
            $link = config('tiqs.frontend_url').'/reset-password?'.http_build_query(['token' => $token, 'email' => $this->email]);
            SendSms::queueMessage((string) $this->country_code, (string) $this->phone, 'Reset your '.config('app.name').' password: '.$link, 'password_reset', $this->id);
        }
    }

    /**
     * Confirming the email also activates the account and the referral reward
     * the referrer earned for this sign-up.
     */
    public function markEmailAsVerified(): bool
    {
        $saved = $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'status' => UserStatus::Active,
        ])->save();

        Referral::query()->where('referred_user_id', $this->id)->update(['is_confirmed' => true]);

        return $saved;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'account_type' => AccountType::class,
            'status' => UserStatus::class,
            'identity_checked_at' => 'datetime',
            'birth_date' => 'date',
            'accepts_individual_tips' => 'boolean',
        ];
    }

    /**
     * Full URL of the profile photo: uploaded to this app ("avatars/…"), or still
     * on the old app (a bare file name in its assets/images/users folder).
     */
    public function avatarUrl(): ?string
    {
        if (blank($this->avatar)) {
            return null;
        }

        if (str_starts_with($this->avatar, 'avatars/')) {
            return Storage::disk('public')->url($this->avatar);
        }

        $legacy = config('tiqs.legacy_assets_url');

        // The old app's placeholder image is not a real photo.
        return $legacy === '' || $this->avatar === 'user-image.jpg' ? null : $legacy.'/images/users/'.rawurlencode($this->avatar);
    }

    /**
     * Checks a password against the account, including passwords still stored
     * as an unsalted md5 hash by the old app (users who have not signed in since).
     */
    public function passwordMatches(string $password): bool
    {
        if ($this->password !== null) {
            return Hash::check($password, $this->password);
        }

        return $this->legacy_password_md5 !== null && hash_equals($this->legacy_password_md5, md5($password));
    }

    /**
     * Create an API token for a device and return its plain text, which is only available now.
     */
    public function issueApiToken(?string $deviceName = null): string
    {
        return $this->createToken(
            $deviceName ?: 'web',
            expiresAt: now()->addDays(config('tiqs.auth.token_lifetime_days')),
        )->plainTextToken;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isBusiness(): bool
    {
        return $this->account_type === AccountType::Business;
    }

    /**
     * @return HasOne<Business, $this>
     */
    public function business(): HasOne
    {
        return $this->hasOne(Business::class, 'owner_id');
    }

    /**
     * Businesses this user works at (or was invited to).
     *
     * @return HasMany<Employee, $this>
     */
    public function employments(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Tips, ratings and reviews this user received.
     *
     * @return HasMany<Tip, $this>
     */
    public function receivedTips(): HasMany
    {
        return $this->hasMany(Tip::class, 'recipient_id');
    }

    /**
     * @return HasMany<Withdrawal, $this>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * @return HasOne<BankAccount, $this>
     */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(BankAccount::class);
    }

    /**
     * @return HasOne<PaymentMethod, $this>
     */
    public function paymentMethod(): HasOne
    {
        return $this->hasOne(PaymentMethod::class);
    }

    /**
     * Payment methods customers can use to tip this user: the user's own choice
     * from the public methods, or all public methods when they made none.
     *
     * @return array<int, array{name: string, icon: string, fee_percent: string, fee_fixed: string, public: bool}> keyed by Pay.nl method id
     */
    public function offeredPaymentMethods(): array
    {
        $public = array_filter(config('payment_methods'), fn (array $method): bool => $method['public']);
        $chosen = $this->paymentMethod?->is_active ? $this->paymentMethod->pay_option_ids : [];
        $offered = array_intersect_key($public, array_flip($chosen));

        return $offered === [] ? $public : $offered;
    }

    /**
     * @return HasOne<QrSetting, $this>
     */
    public function qrSetting(): HasOne
    {
        return $this->hasOne(QrSetting::class);
    }

    /**
     * @return HasOne<TipPageSetting, $this>
     */
    public function tipPageSetting(): HasOne
    {
        return $this->hasOne(TipPageSetting::class);
    }

    /**
     * @return HasOne<Vcard, $this>
     */
    public function vcard(): HasOne
    {
        return $this->hasOne(Vcard::class);
    }

    /**
     * @return HasOne<VcardTheme, $this>
     */
    public function vcardTheme(): HasOne
    {
        return $this->hasOne(VcardTheme::class);
    }

    /**
     * @return HasOne<VcardDraft, $this>
     */
    public function vcardDraft(): HasOne
    {
        return $this->hasOne(VcardDraft::class);
    }

    /**
     * @return HasMany<VcardAssistantMessage, $this>
     */
    public function vcardAssistantMessages(): HasMany
    {
        return $this->hasMany(VcardAssistantMessage::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /**
     * @return HasMany<UserSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }
}
