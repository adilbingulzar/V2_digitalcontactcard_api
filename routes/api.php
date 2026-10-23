<?php

use App\Http\Controllers\Api\V1\Admin\AccountImportController;
use App\Http\Controllers\Api\V1\Admin\AiSettingsController;
use App\Http\Controllers\Api\V1\Admin\AiUsageController;
use App\Http\Controllers\Api\V1\Admin\BusinessController as AdminBusinessController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\EmailTemplateController;
use App\Http\Controllers\Api\V1\Admin\FeeController;
use App\Http\Controllers\Api\V1\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Admin\SmsController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\TranslationController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Admin\VisitorLogController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\SsoSessionController;
use App\Http\Controllers\Api\V1\Auth\VerificationCodeController;
use App\Http\Controllers\Api\V1\Business\AgreementController;
use App\Http\Controllers\Api\V1\Business\DefaultAgreementController;
use App\Http\Controllers\Api\V1\Business\EmployeeController;
use App\Http\Controllers\Api\V1\Business\EmployeeShiftController;
use App\Http\Controllers\Api\V1\Business\JoinRequestController;
use App\Http\Controllers\Api\V1\Business\ReportController;
use App\Http\Controllers\Api\V1\Business\RoleController;
use App\Http\Controllers\Api\V1\Business\SpotController as BusinessSpotController;
use App\Http\Controllers\Api\V1\Business\SpotExportController;
use App\Http\Controllers\Api\V1\Business\SpotStaffController;
use App\Http\Controllers\Api\V1\Business\WidgetSettingController;
use App\Http\Controllers\Api\V1\Me\AccountController;
use App\Http\Controllers\Api\V1\Me\AccountStatusController;
use App\Http\Controllers\Api\V1\Me\AvatarController;
use App\Http\Controllers\Api\V1\Me\BankAccountController;
use App\Http\Controllers\Api\V1\Me\BusinessController;
use App\Http\Controllers\Api\V1\Me\DashboardController;
use App\Http\Controllers\Api\V1\Me\EmailController;
use App\Http\Controllers\Api\V1\Me\EmploymentController;
use App\Http\Controllers\Api\V1\Me\EmploymentLiveController;
use App\Http\Controllers\Api\V1\Me\EmploymentShiftController;
use App\Http\Controllers\Api\V1\Me\InvitationController;
use App\Http\Controllers\Api\V1\Me\NotificationController;
use App\Http\Controllers\Api\V1\Me\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Me\PasswordController;
use App\Http\Controllers\Api\V1\Me\PayoutController;
use App\Http\Controllers\Api\V1\Me\PayoutSettingController;
use App\Http\Controllers\Api\V1\Me\ProfileController as MeProfileController;
use App\Http\Controllers\Api\V1\Me\PushSubscriptionController;
use App\Http\Controllers\Api\V1\Me\QrSettingController;
use App\Http\Controllers\Api\V1\Me\ReviewController;
use App\Http\Controllers\Api\V1\Me\SubscriptionController;
use App\Http\Controllers\Api\V1\Me\TipController;
use App\Http\Controllers\Api\V1\Me\TipExportController;
use App\Http\Controllers\Api\V1\Me\TipPageBackgroundController;
use App\Http\Controllers\Api\V1\Me\TipPageController;
use App\Http\Controllers\Api\V1\Me\VcardAssistantController;
use App\Http\Controllers\Api\V1\Me\VcardController as MeVcardController;
use App\Http\Controllers\Api\V1\Me\VcardDraftController;
use App\Http\Controllers\Api\V1\Me\VcardHoursController;
use App\Http\Controllers\Api\V1\Me\VcardImageController;
use App\Http\Controllers\Api\V1\Me\VcardMediaController;
use App\Http\Controllers\Api\V1\Me\VerificationController;
use App\Http\Controllers\Api\V1\Me\WithdrawalController;
use App\Http\Controllers\Api\V1\Me\WorkSpotController;
use App\Http\Controllers\Api\V1\Public\PageExistsController;
use App\Http\Controllers\Api\V1\Public\PaymentController;
use App\Http\Controllers\Api\V1\Public\ProfileController;
use App\Http\Controllers\Api\V1\Public\ProfileReviewController;
use App\Http\Controllers\Api\V1\Public\ReviewWidgetController;
use App\Http\Controllers\Api\V1\Public\SpotCheckoutController;
use App\Http\Controllers\Api\V1\Public\SpotController;
use App\Http\Controllers\Api\V1\Public\SpotQuoteController;
use App\Http\Controllers\Api\V1\Public\SpotReviewController;
use App\Http\Controllers\Api\V1\Public\TermsController;
use App\Http\Controllers\Api\V1\Public\TipCheckoutController;
use App\Http\Controllers\Api\V1\Public\TipQuoteController;
use App\Http\Controllers\Api\V1\Public\TranslationController as PublicTranslationController;
use App\Http\Controllers\Api\V1\Public\VcardController;
use App\Http\Controllers\Api\V1\Public\VcardFileController;
use App\Http\Controllers\Api\V1\Public\WalletPassController;
use App\Http\Controllers\Api\V1\Webhooks\PayNlWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\SumsubWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Public tip and review pages: no account needed.
    Route::prefix('public')->name('public.')->middleware('throttle:public')->group(function (): void {
        Route::get('profiles/{user:slug}', [ProfileController::class, 'show'])->name('profiles.show');
        Route::get('terms', [TermsController::class, 'show'])->name('terms.show');
        Route::get('page-exists', PageExistsController::class)->name('page-exists');
        Route::get('widgets/review/{business}', [ReviewWidgetController::class, 'show'])->name('widgets.review');
        Route::get('locales', [PublicTranslationController::class, 'locales'])->name('locales');
        Route::get('translations/{locale}', [PublicTranslationController::class, 'show'])->name('translations.show');
        Route::get('vcards/by-id/{userId}', [VcardController::class, 'slug'])->whereNumber('userId')->name('vcards.slug');
        Route::get('vcards/{user:slug}', [VcardController::class, 'show'])->name('vcards.show');
        Route::get('vcards/{user:slug}/vcf', [VcardFileController::class, 'show'])->name('vcards.vcf');
        Route::get('vcards/{user:slug}/wallet/google', [WalletPassController::class, 'google'])->name('vcards.wallet.google');
        Route::get('vcards/{user:slug}/wallet/apple', [WalletPassController::class, 'apple'])->name('vcards.wallet.apple');
        Route::get('profiles/{user:slug}/quote', [TipQuoteController::class, 'show'])->name('profiles.quote');
        Route::post('profiles/{user:slug}/reviews', [ProfileReviewController::class, 'store'])
            ->middleware('throttle:public-write')
            ->name('profiles.reviews.store');
        Route::post('profiles/{user:slug}/checkout', [TipCheckoutController::class, 'store'])
            ->middleware('throttle:public-write')
            ->name('profiles.checkout.store');
        Route::get('payments/{payment:uuid}', [PaymentController::class, 'show'])->name('payments.show');

        // Spots: /paytips/spot/{business id}/{spot slug} on printed QR codes. The spot must belong to the business.
        Route::prefix('businesses/{business}/spots/{spot:slug}')->name('spots.')->scopeBindings()->group(function (): void {
            Route::get('/', [SpotController::class, 'show'])->name('show');
            Route::get('quote', [SpotQuoteController::class, 'show'])->name('quote');
            Route::post('reviews', [SpotReviewController::class, 'store'])->middleware('throttle:public-write')->name('reviews.store');
            Route::post('checkout', [SpotCheckoutController::class, 'store'])->middleware('throttle:public-write')->name('checkout.store');
        });
    });

    Route::post('webhooks/paynl', PayNlWebhookController::class)->name('webhooks.paynl');
    Route::post('webhooks/sumsub', SumsubWebhookController::class)->name('webhooks.sumsub');

    // The signed-in user's own data. Requires a confirmed email.
    Route::prefix('me')->name('me.')->middleware(['auth:sanctum', 'verified'])->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
        Route::put('employments/{employee}/live', [EmploymentLiveController::class, 'update'])->name('employments.live');
        Route::get('employments/{employee}/shifts', [EmploymentShiftController::class, 'show'])->name('employments.shifts.show');
        Route::put('employments/{employee}/shifts', [EmploymentShiftController::class, 'update'])->name('employments.shifts.update');
        Route::get('tips', [TipController::class, 'index'])->name('tips.index');
        Route::get('tips/export', [TipExportController::class, 'show'])->name('tips.export');
        Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('withdrawals', [PayoutController::class, 'store'])->middleware('throttle:payouts')->name('withdrawals.store');
        Route::get('payouts', [PayoutController::class, 'show'])->name('payouts.show');
        Route::put('payout-settings', [PayoutSettingController::class, 'update'])->name('payout-settings.update');
        Route::get('bank-account', [BankAccountController::class, 'show'])->name('bank-account.show');
        Route::put('bank-account', [BankAccountController::class, 'update'])->name('bank-account.update');

        Route::patch('profile', [MeProfileController::class, 'update'])->name('profile.update');
        Route::post('avatar', [AvatarController::class, 'store'])->middleware('throttle:uploads')->name('avatar.store');
        Route::delete('avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
        Route::patch('business', [BusinessController::class, 'update'])->name('business.update');
        Route::get('tip-page', [TipPageController::class, 'show'])->name('tip-page.show');
        Route::put('tip-page', [TipPageController::class, 'update'])->name('tip-page.update');
        Route::post('tip-page/background', [TipPageBackgroundController::class, 'store'])->middleware('throttle:uploads')->name('tip-page.background.store');
        Route::delete('tip-page/background', [TipPageBackgroundController::class, 'destroy'])->name('tip-page.background.destroy');
        Route::get('qr', [QrSettingController::class, 'show'])->name('qr.show');
        Route::put('qr', [QrSettingController::class, 'update'])->name('qr.update');

        // Where the user works ("My work"): invitations, join requests, leaving.
        Route::get('vcard', [MeVcardController::class, 'show'])->name('vcard.show');
        Route::put('vcard', [MeVcardController::class, 'update'])->name('vcard.update');
        Route::post('vcard/{type}', [VcardImageController::class, 'store'])->whereIn('type', ['logo', 'banner'])->middleware('throttle:uploads')->name('vcard.image.store');
        Route::delete('vcard/{type}', [VcardImageController::class, 'destroy'])->whereIn('type', ['logo', 'banner'])->name('vcard.image.destroy');
        Route::get('vcard-hours', [VcardHoursController::class, 'show'])->name('vcard-hours.show');
        Route::put('vcard-hours', [VcardHoursController::class, 'update'])->name('vcard-hours.update');
        Route::get('vcard-draft', [VcardDraftController::class, 'show'])->name('vcard-draft.show');
        Route::put('vcard-draft', [VcardDraftController::class, 'update'])->name('vcard-draft.update');
        Route::delete('vcard-draft', [VcardDraftController::class, 'destroy'])->name('vcard-draft.destroy');
        Route::post('vcard-media', [VcardMediaController::class, 'store'])->middleware('throttle:uploads')->name('vcard-media.store');
        Route::get('vcard-assistant', [VcardAssistantController::class, 'index'])->name('vcard-assistant.index');
        Route::post('vcard-assistant', [VcardAssistantController::class, 'store'])->middleware('throttle:card-assistant')->name('vcard-assistant.store');
        Route::post('vcard-assistant/import', [VcardAssistantController::class, 'import'])->middleware('throttle:card-assistant')->name('vcard-assistant.import');
        Route::delete('vcard-assistant', [VcardAssistantController::class, 'destroy'])->name('vcard-assistant.destroy');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'show'])->name('notification-preferences.show');
        Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
        Route::put('password', [PasswordController::class, 'update'])->middleware('throttle:account')->name('password.update');
        Route::delete('account', [AccountController::class, 'destroy'])->middleware('throttle:account')->name('account.destroy');
        Route::put('account/status', [AccountStatusController::class, 'update'])->middleware('throttle:account')->name('account.status');

        Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
        Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware('throttle:payouts')->name('subscription.checkout');
        Route::get('push', [PushSubscriptionController::class, 'show'])->name('push.show');
        Route::post('push', [PushSubscriptionController::class, 'store'])->middleware('throttle:invites')->name('push.store');
        Route::delete('push', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
        Route::get('verification', [VerificationController::class, 'show'])->name('verification.show');
        Route::post('verification/token', [VerificationController::class, 'token'])->middleware('throttle:verification-code')->name('verification.token');
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::put('reviews/{tip}/pin', [ReviewController::class, 'pin'])->name('reviews.pin');
        Route::get('spots', [WorkSpotController::class, 'index'])->name('spots.index');
        Route::put('spots/{spotEmployee}/live', [WorkSpotController::class, 'live'])->name('spots.live');
        Route::delete('spots/{spotEmployee}', [WorkSpotController::class, 'destroy'])->name('spots.destroy');
        Route::get('employments', [EmploymentController::class, 'index'])->name('employments.index');
        Route::post('employments', [EmploymentController::class, 'store'])->middleware('throttle:invites')->name('employments.store');
        Route::delete('employments/{employee}', [EmploymentController::class, 'destroy'])->name('employments.destroy');
        Route::post('employments/{employee}/accept', [InvitationController::class, 'accept'])->name('employments.accept');
        Route::post('employments/{employee}/decline', [InvitationController::class, 'decline'])->name('employments.decline');

        // The business the user owns. Every record must belong to it (see Business\BusinessController).
        Route::prefix('business')->name('business.')->group(function (): void {
            Route::apiResource('roles', RoleController::class)->except('show');
            Route::apiResource('agreements', AgreementController::class)->except('show');
            Route::post('agreements/{agreement}/default', [DefaultAgreementController::class, 'store'])->name('agreements.default');
            Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::post('employees', [EmployeeController::class, 'store'])->middleware('throttle:invites')->name('employees.store');
            Route::patch('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
            Route::post('employees/{employee}/accept', [JoinRequestController::class, 'accept'])->name('employees.accept');
            Route::post('employees/{employee}/reject', [JoinRequestController::class, 'reject'])->name('employees.reject');
            Route::get('employees/{employee}/shifts', [EmployeeShiftController::class, 'show'])->name('employees.shifts.show');
            Route::put('employees/{employee}/shifts', [EmployeeShiftController::class, 'update'])->name('employees.shifts.update');
            Route::post('spots/bulk', [BusinessSpotController::class, 'bulk'])->name('spots.bulk');
            Route::apiResource('spots', BusinessSpotController::class)->except('show');
            Route::put('spots/{spot}/staff', [SpotStaffController::class, 'update'])->name('spots.staff.update');
            Route::get('spot-staff/{spotEmployee}/shifts', [EmployeeShiftController::class, 'showForSpot'])->name('spot-staff.shifts.show');
            Route::put('spot-staff/{spotEmployee}/shifts', [EmployeeShiftController::class, 'updateForSpot'])->name('spot-staff.shifts.update');
            Route::get('report', [ReportController::class, 'show'])->name('report');
            Route::get('spots-export', [SpotExportController::class, 'spots'])->name('spots.export');
            Route::get('spot-tips-export', [SpotExportController::class, 'tips'])->name('spot-tips.export');
            Route::get('widget', [WidgetSettingController::class, 'show'])->name('widget.show');
            Route::put('widget', [WidgetSettingController::class, 'update'])->name('widget.update');
        });
    });

    Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'verified', 'can:admin'])->group(function (): void {
        Route::get('dashboard', [AdminDashboardController::class, 'show'])->name('dashboard');
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->withTrashed()->name('users.show');
        Route::put('users/{user}/fees', [AdminUserController::class, 'updateFees'])->name('users.fees');
        Route::get('businesses', [AdminBusinessController::class, 'index'])->name('businesses.index');
        Route::get('payouts', [AdminPayoutController::class, 'index'])->name('payouts.index');
        Route::post('payouts/mark-paid', [AdminPayoutController::class, 'markPaid'])->name('payouts.mark-paid');
        Route::post('payouts/export', [AdminPayoutController::class, 'export'])->name('payouts.export');
        Route::get('fees', [FeeController::class, 'index'])->name('fees.index');
        Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions', [AdminSubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::delete('subscriptions/{subscription}', [AdminSubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
        Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('ai-settings', [AiSettingsController::class, 'show'])->name('ai-settings.show');
        Route::put('ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
        Route::post('ai-settings/models', [AiSettingsController::class, 'models'])->middleware('throttle:invites')->name('ai-settings.models');
        Route::post('ai-settings/test', [AiSettingsController::class, 'test'])->middleware('throttle:invites')->name('ai-settings.test');
        Route::get('ai-usage', [AiUsageController::class, 'index'])->name('ai-usage.index');
        Route::get('ai-usage/export', [AiUsageController::class, 'export'])->name('ai-usage.export');
        Route::get('translations', [TranslationController::class, 'index'])->name('translations.index');
        Route::put('translations', [TranslationController::class, 'update'])->name('translations.update');
        Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::put('email-templates/{name}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::delete('email-templates/{name}', [EmailTemplateController::class, 'destroy'])->name('email-templates.destroy');
        Route::get('visitor-logs', [VisitorLogController::class, 'index'])->name('visitor-logs.index');
        Route::get('sms', [SmsController::class, 'index'])->name('sms.index');
        Route::post('sms', [SmsController::class, 'store'])->middleware('throttle:invites')->name('sms.store');
        Route::get('account-imports/template', [AccountImportController::class, 'template'])->name('account-imports.template');
        Route::post('account-imports', [AccountImportController::class, 'store'])->middleware('throttle:uploads')->name('account-imports.store');
    });

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [RegisteredUserController::class, 'store'])
            ->middleware('throttle:register')
            ->name('register');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login');
        Route::post('sso', [SsoSessionController::class, 'store'])->name('sso');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:password-reset')
            ->name('password.email');
        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:password-reset')
            ->name('password.store');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [CurrentUserController::class, 'show'])->name('me');
            Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
            Route::post('email/verify', [EmailVerificationController::class, 'store'])
                ->middleware('throttle:verify-email')
                ->name('verification.verify');
            Route::post('email/resend', [VerificationCodeController::class, 'store'])
                ->middleware('throttle:verification-code')
                ->name('verification.send');
            // Outside the "verified" group, so a mistyped new address can be corrected.
            Route::put('email', [EmailController::class, 'update'])
                ->middleware('throttle:verification-code')
                ->name('email.update');
        });
    });
});
