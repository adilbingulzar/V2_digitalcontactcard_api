<?php

namespace App\Services\Ai;

use App\Models\AppSetting;
use InvalidArgumentException;

/**
 * Creates the AI provider admins chose in Admin › Settings.
 */
class AiProviders
{
    public function make(string $provider, string $apiKey, ?string $model = null): AiProvider
    {
        return match ($provider) {
            'anthropic' => new AnthropicProvider($apiKey, $model),
            'openai' => new OpenAiProvider($apiKey, $model),
            default => throw new InvalidArgumentException("Unknown AI provider [{$provider}]."),
        };
    }

    /**
     * The provider and model users chat with.
     *
     * @throws AiException
     */
    public function current(): AiProvider
    {
        $settings = AppSetting::current();
        if (! $settings->isAiReady()) {
            throw new AiException('The assistant is not set up.');
        }

        return $this->make($settings->ai_provider, (string) $settings->aiApiKey($settings->ai_provider), $settings->aiModel($settings->ai_provider));
    }
}
