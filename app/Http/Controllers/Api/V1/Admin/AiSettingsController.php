<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AiUsageLog;
use App\Models\AppSetting;
use App\Services\Ai\AiException;
use App\Services\Ai\AiProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The AI assistant of the card builder: on or off, Anthropic or OpenAI, the
 * model, each provider's API key, and how many messages a user may send a day.
 * Keys are stored encrypted and never sent back; only their last characters are.
 */
class AiSettingsController extends AdminController
{
    private const string MODEL_PATTERN = 'regex:/^[A-Za-z0-9._:\/-]+$/';

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->data(AppSetting::current())]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_enabled' => ['required', 'boolean'],
            'ai_provider' => ['required', Rule::in(AppSetting::AI_PROVIDERS)],
            'anthropic_model' => ['nullable', 'string', 'max:100', self::MODEL_PATTERN],
            'openai_model' => ['nullable', 'string', 'max:100', self::MODEL_PATTERN],
            'anthropic_api_key' => ['nullable', 'string', 'max:500'],
            'openai_api_key' => ['nullable', 'string', 'max:500'],
            'remove_anthropic_api_key' => ['sometimes', 'boolean'],
            'remove_openai_api_key' => ['sometimes', 'boolean'],
            'ai_daily_limit' => ['required', 'integer', 'between:1,1000'],
            'anthropic_input_price' => ['nullable', 'numeric', 'between:0,1000'],
            'anthropic_output_price' => ['nullable', 'numeric', 'between:0,1000'],
            'openai_input_price' => ['nullable', 'numeric', 'between:0,1000'],
            'openai_output_price' => ['nullable', 'numeric', 'between:0,1000'],
        ]);

        $settings = AppSetting::current();
        $settings->fill([
            'ai_enabled' => $validated['ai_enabled'],
            'ai_provider' => $validated['ai_provider'],
            'anthropic_model' => $validated['anthropic_model'] ?? null,
            'openai_model' => $validated['openai_model'] ?? null,
            'ai_daily_limit' => $validated['ai_daily_limit'],
            'anthropic_input_price' => $validated['anthropic_input_price'] ?? null,
            'anthropic_output_price' => $validated['anthropic_output_price'] ?? null,
            'openai_input_price' => $validated['openai_input_price'] ?? null,
            'openai_output_price' => $validated['openai_output_price'] ?? null,
        ]);

        foreach (AppSetting::AI_PROVIDERS as $provider) {
            $key = trim((string) ($validated["{$provider}_api_key"] ?? ''));
            if ($request->boolean("remove_{$provider}_api_key")) {
                $settings->{"{$provider}_api_key"} = null;
            } elseif ($key !== '') {
                $settings->{"{$provider}_api_key"} = $key;
            }
        }

        if ($settings->ai_enabled && ! $settings->isAiReady()) {
            throw ValidationException::withMessages(['ai_enabled' => 'Add an API key and choose a model for the selected provider before switching the assistant on.']);
        }

        $settings->save();

        return response()->json(['data' => $this->data($settings)]);
    }

    /**
     * The models a key can use: with a key just typed in, or the saved one. Also shows whether the key works.
     */
    public function models(Request $request, AiProviders $providers): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(AppSetting::AI_PROVIDERS)],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $provider = $providers->make($validated['provider'], $this->apiKey($validated['provider'], $validated['api_key'] ?? null));

        try {
            return response()->json(['data' => $provider->models()]);
        } catch (AiException $exception) {
            throw ValidationException::withMessages(['api_key' => $exception->getMessage()]);
        }
    }

    /**
     * Sends one small message to the model, so admins know key and model work before users rely on them.
     */
    public function test(Request $request, AiProviders $providers): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(AppSetting::AI_PROVIDERS)],
            'model' => ['required', 'string', 'max:100', self::MODEL_PATTERN],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $provider = $providers->make($validated['provider'], $this->apiKey($validated['provider'], $validated['api_key'] ?? null), $validated['model']);

        try {
            $completion = $provider->complete(
                'You check that a connection works. Answer in English.',
                [['role' => 'user', 'content' => 'Say hello in five words or fewer.']],
                ['type' => 'object', 'properties' => ['reply' => ['type' => 'string']], 'required' => ['reply'], 'additionalProperties' => false],
            );
        } catch (AiException $exception) {
            if ($exception->usage !== null) {
                AiUsageLog::record($request->user(), AiUsageLog::FEATURE_CONNECTION_TEST, $validated['provider'], $exception->usage, succeeded: false);
            }

            throw ValidationException::withMessages(['model' => $exception->getMessage()]);
        }
        AiUsageLog::record($request->user(), AiUsageLog::FEATURE_CONNECTION_TEST, $validated['provider'], $completion);

        return response()->json(['data' => ['reply' => (string) ($completion->data['reply'] ?? ''), 'model' => $completion->model]]);
    }

    /**
     * @throws ValidationException
     */
    private function apiKey(string $provider, ?string $typed): string
    {
        $key = filled($typed) ? trim($typed) : AppSetting::current()->aiApiKey($provider);
        if (blank($key)) {
            throw ValidationException::withMessages(['api_key' => 'Enter the API key first.']);
        }

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(AppSetting $settings): array
    {
        $hint = fn (?string $key): ?string => filled($key) ? '…'.substr($key, -4) : null;

        return [
            'ai_enabled' => $settings->ai_enabled,
            'ai_provider' => $settings->ai_provider,
            'anthropic_model' => $settings->anthropic_model,
            'openai_model' => $settings->openai_model,
            'anthropic_api_key_hint' => $hint($settings->anthropic_api_key),
            'openai_api_key_hint' => $hint($settings->openai_api_key),
            'ai_daily_limit' => $settings->ai_daily_limit,
            'anthropic_input_price' => $settings->anthropic_input_price,
            'anthropic_output_price' => $settings->anthropic_output_price,
            'openai_input_price' => $settings->openai_input_price,
            'openai_output_price' => $settings->openai_output_price,
            'is_ready' => $settings->isAiReady(),
        ];
    }
}
