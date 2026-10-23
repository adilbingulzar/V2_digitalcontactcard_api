<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Mail\VerificationCodeMail;
use App\Models\AppSetting;
use App\Models\EmailTemplate;
use App\Models\Translation;
use App\Models\User;
use App\Models\VisitorLog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Settings, translations, email templates and visitor logs.
 */
class ContentControllersTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'tip_fee_percent' => 0.5, 'tip_fee_fixed' => 0, 'user_subscription_required' => false, 'business_subscription_required' => true,
            'user_plan_monthly' => 5.55, 'user_plan_yearly' => 55.55, 'business_plan_monthly' => 9.95, 'business_plan_yearly' => 99.95,
            'free_trial_days' => 30, 'support_email' => 'help@tiqs.test', 'terms_and_conditions' => '<p>Terms</p>',
            'payout_debtor_name' => 'TIQS B.V.', 'payout_debtor_iban' => 'nl91 abna 0417 1643 00', 'payout_debtor_bic' => 'rabonl2u',
        ], $overrides);
    }

    public function test_saves_settings_with_the_debtor_iban_encrypted(): void
    {
        $this->putJson(route('api.v1.admin.settings.update'), $this->settings())
            ->assertOk()
            ->assertJsonPath('data.payout_debtor_iban', 'NL91ABNA0417164300')
            ->assertJsonPath('data.payout_debtor_bic', 'RABONL2U')
            ->assertJsonPath('data.business_subscription_required', true);

        $this->assertNotSame('NL91ABNA0417164300', DB::table('app_settings')->value('payout_debtor_iban'));
        $this->assertSame('NL91ABNA0417164300', AppSetting::current()->payout_debtor_iban);
    }

    public function test_rejects_an_invalid_debtor_iban(): void
    {
        $this->putJson(route('api.v1.admin.settings.update'), $this->settings(['payout_debtor_iban' => 'NL00ABNA0417164300']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payout_debtor_iban');
    }

    public function test_lists_missing_translations_and_saves_or_removes_one(): void
    {
        Translation::factory()->create(['locale' => 'en', 'key' => 'Hello', 'text' => 'Hello']);
        Translation::factory()->create(['locale' => 'en', 'key' => 'Thanks', 'text' => 'Thanks']);
        Translation::factory()->create(['locale' => 'nl', 'key' => 'Thanks', 'text' => 'Bedankt']);

        $this->getJson(route('api.v1.admin.translations.index', ['locale' => 'nl', 'missing' => 1]))
            ->assertOk()
            ->assertJsonPath('data.*.key', ['Hello'])
            ->assertJsonPath('meta.locale', 'nl');

        $this->putJson(route('api.v1.admin.translations.update'), ['locale' => 'nl', 'key' => 'Hello', 'text' => 'Hallo'])->assertOk();
        $this->assertSame('Hallo', Translation::where('locale', 'nl')->where('key', 'Hello')->value('text'));

        $this->putJson(route('api.v1.admin.translations.update'), ['locale' => 'nl', 'key' => 'Hello', 'text' => ''])->assertOk();
        $this->assertFalse(Translation::where('locale', 'nl')->where('key', 'Hello')->exists());

        $this->putJson(route('api.v1.admin.translations.update'), ['locale' => 'nl', 'key' => 'Not a key', 'text' => 'x'])->assertUnprocessable();
    }

    public function test_an_edited_template_replaces_the_built_in_email(): void
    {
        $this->getJson(route('api.v1.admin.email-templates.index'))->assertOk()->assertJsonPath('data.0.name', 'verification_code')->assertJsonPath('data.0.is_custom', false);

        $this->putJson(route('api.v1.admin.email-templates.update', 'verification_code'), [
            'subject' => 'Code for {name}',
            'html' => '<p onclick="x()">Dag {name}, jouw code: <strong>{code}</strong></p><script>alert(1)</script><a href="{link}">link</a>',
        ])->assertOk();

        $template = EmailTemplate::where('name', 'verification_code')->sole();
        $this->assertSame('<p>Dag {name}, jouw code: <strong>{code}</strong></p><a href="{link}" rel="noopener noreferrer">link</a>', $template->html);

        $mail = new VerificationCodeMail('<b>Anna</b>', '123456', 15);
        $mail->assertHasSubject('Code for <b>Anna</b>');
        $mail->assertSeeInHtml('Dag &lt;b&gt;Anna&lt;/b&gt;, jouw code:', false);
        $mail->assertSeeInHtml('123456');
        $mail->assertDontSeeInHtml('<b>Anna</b>', false);

        $this->deleteJson(route('api.v1.admin.email-templates.destroy', 'verification_code'))->assertNoContent();
        (new VerificationCodeMail('Anna', '123456', 15))->assertSeeInHtml('Use this code to confirm your email address');
        $this->putJson(route('api.v1.admin.email-templates.update', 'unknown'), ['subject' => 'x', 'html' => 'x'])->assertNotFound();
    }

    public function test_counts_visits_per_page_type(): void
    {
        VisitorLog::factory()->count(2)->create(['page_type' => 'paytip']);
        VisitorLog::factory()->create(['page_type' => 'review']);

        $this->getJson(route('api.v1.admin.visitor-logs.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.by_type.paytip', 2);
    }
}
