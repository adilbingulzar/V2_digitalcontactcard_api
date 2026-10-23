<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Models\AiUsageLog;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Ai\AiCompletion;
use App\Services\Ai\AiException;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviders;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class AiSettingsControllerTest extends TestCase
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
            'ai_enabled' => true,
            'ai_provider' => 'anthropic',
            'anthropic_model' => 'claude-opus-5-5',
            'openai_model' => null,
            'anthropic_api_key' => 'sk-ant-api03-secret-9f3A',
            'openai_api_key' => '',
            'ai_daily_limit' => 25,
            'anthropic_input_price' => 4,
            'anthropic_output_price' => 20,
        ], $overrides);
    }

    public function test_saves_keys_encrypted_and_only_shows_their_end(): void
    {
        $this->putJson(route('api.v1.admin.ai-settings.update'), $this->settings())
            ->assertOk()
            ->assertJsonPath('data.anthropic_api_key_hint', '…9f3A')
            ->assertJsonPath('data.openai_api_key_hint', null)
            ->assertJsonPath('data.is_ready', true)
            ->assertJsonPath('data.anthropic_input_price', '4.0000')
            ->assertJsonPath('data.openai_output_price', null)
            ->assertJsonMissingPath('data.anthropic_api_key');

        $this->assertNotSame('sk-ant-api03-secret-9f3A', DB::table('app_settings')->value('anthropic_api_key'));
        $this->assertSame('sk-ant-api03-secret-9f3A', AppSetting::current()->anthropic_api_key);

        $this->getJson(route('api.v1.admin.ai-settings.show'))->assertOk()->assertDontSee('secret');
    }

    public function test_keeps_a_saved_key_when_left_empty_and_removes_it_on_request(): void
    {
        $this->putJson(route('api.v1.admin.ai-settings.update'), $this->settings())->assertOk();

        $this->putJson(route('api.v1.admin.ai-settings.update'), $this->settings(['anthropic_api_key' => '', 'ai_daily_limit' => 10]))
            ->assertOk()
            ->assertJsonPath('data.anthropic_api_key_hint', '…9f3A')
            ->assertJsonPath('data.ai_daily_limit', 10);

        $this->putJson(route('api.v1.admin.ai-settings.update'), $this->settings(['ai_enabled' => false, 'anthropic_api_key' => '', 'remove_anthropic_api_key' => true]))
            ->assertOk()
            ->assertJsonPath('data.anthropic_api_key_hint', null);
        $this->assertNull(AppSetting::current()->anthropic_api_key);
    }

    public function test_cannot_switch_on_without_a_key_and_model_for_the_chosen_provider(): void
    {
        $this->putJson(route('api.v1.admin.ai-settings.update'), $this->settings(['ai_provider' => 'openai']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ai_enabled');

        $this->putJson(route('api.v1.admin.ai-settings.update'), $this->settings(['ai_provider' => 'gemini']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ai_provider');

        $this->assertFalse(AppSetting::current()->ai_enabled);
    }

    public function test_lists_models_with_the_saved_key_and_tests_a_model(): void
    {
        AppSetting::current()->update(['openai_api_key' => 'sk-openai-saved']);
        $provider = $this->mock(AiProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('models')->andReturn([['id' => 'gpt-5.1', 'name' => 'gpt-5.1']]);
            $mock->shouldReceive('complete')->andReturn(new AiCompletion(['reply' => 'Hello there!'], 'gpt-5.1', 20, 5));
        });
        $this->mock(AiProviders::class, function (MockInterface $mock) use ($provider): void {
            $mock->shouldReceive('make')->with('openai', 'sk-openai-saved')->andReturn($provider);
            $mock->shouldReceive('make')->with('openai', 'sk-openai-saved', 'gpt-5.1')->andReturn($provider);
        });

        $this->postJson(route('api.v1.admin.ai-settings.models'), ['provider' => 'openai'])
            ->assertOk()
            ->assertJsonPath('data.0.id', 'gpt-5.1');

        $this->postJson(route('api.v1.admin.ai-settings.test'), ['provider' => 'openai', 'model' => 'gpt-5.1'])
            ->assertOk()
            ->assertJsonPath('data.reply', 'Hello there!');

        $log = AiUsageLog::sole();
        $this->assertSame(['connection_test', 'openai', 20, 5, null], [$log->feature, $log->provider, $log->input_tokens, $log->output_tokens, $log->cost]);
    }

    public function test_shows_why_a_key_does_not_work(): void
    {
        $provider = $this->mock(AiProvider::class, fn (MockInterface $mock) => $mock->shouldReceive('models')->andThrow(new AiException('Anthropic did not accept the API key.')));
        $this->mock(AiProviders::class, fn (MockInterface $mock) => $mock->shouldReceive('make')->with('anthropic', 'sk-wrong')->andReturn($provider));

        $this->postJson(route('api.v1.admin.ai-settings.models'), ['provider' => 'anthropic', 'api_key' => 'sk-wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.api_key.0', 'Anthropic did not accept the API key.');

        $this->postJson(route('api.v1.admin.ai-settings.models'), ['provider' => 'openai'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.api_key.0', 'Enter the API key first.');
    }
}
