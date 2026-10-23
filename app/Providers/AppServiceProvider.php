<?php

namespace App\Providers;

use App\Mail\PasswordResetMail;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());
        TrustProxies::at(config('tiqs.trusted_proxies'));

        // A few accounts from the old app share an email address. Lookups by email
        // (password reset) then use the oldest account, as the old app did.
        Auth::provider('eloquent-oldest-first', fn (Application $app, array $config): EloquentUserProvider => (new EloquentUserProvider($app['hash'], $config['model']))
            ->withQuery(fn (Builder $query): Builder => $query->orderBy('id')));

        $this->configureRateLimiting();

        // Reset links open the Next.js page, which posts the token back to the API.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => config('tiqs.frontend_url').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
        // The reset email uses the admin-editable template.
        ResetPassword::toMailUsing(fn (User $user, string $token): PasswordResetMail => (new PasswordResetMail(
            $user->name,
            config('tiqs.frontend_url').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]),
            (int) config('auth.passwords.users.expire', 60),
        ))->to($user->email));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request): array => [
            Limit::perMinute(3)->by(Str::lower($request->string('email')->toString()).'|'.$request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);

        RateLimiter::for('verify-email', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->id));

        // Password-protected account changes: slows down guessing with a stolen session.
        RateLimiter::for('account', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->user()?->id));

        RateLimiter::for('verification-code', fn (Request $request): Limit => Limit::perMinutes(5, 3)->by((string) $request->user()?->id));

        RateLimiter::for('public', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('payouts', fn (Request $request): Limit => Limit::perMinute(3)->by((string) $request->user()?->id));

        RateLimiter::for('invites', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->id));

        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->id));

        RateLimiter::for('public-write', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        // Every message to the card assistant costs money: a few per minute, and the daily number admins set.
        RateLimiter::for('card-assistant', fn (Request $request): array => [
            Limit::perMinute(6)->by('minute:'.$request->user()?->id),
            Limit::perDay(max(1, AppSetting::current()->ai_daily_limit))->by('day:'.$request->user()?->id),
        ]);
    }
}
