<?php

namespace App\Models;

use Database\Factories\AppSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide settings. The table holds a single row; use AppSetting::current().
 */
#[Fillable([
    'tip_fee_fixed', 'tip_fee_percent', 'business_subscription_required', 'user_subscription_required',
    'support_url', 'support_email', 'terms_and_conditions', 'user_plan_monthly', 'user_plan_yearly',
    'business_plan_monthly', 'business_plan_yearly', 'free_trial_days', 'payout_debtor_name', 'payout_debtor_iban',
    'payout_debtor_bic', 'ai_enabled', 'ai_provider', 'anthropic_api_key', 'anthropic_model', 'openai_api_key', 'openai_model',
    'ai_daily_limit', 'anthropic_input_price', 'anthropic_output_price', 'openai_input_price', 'openai_output_price',
])]
class AppSetting extends Model
{
    /** @use HasFactory<AppSettingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tip_fee_fixed' => 'decimal:2',
            'tip_fee_percent' => 'decimal:2',
            'business_subscription_required' => 'boolean',
            'user_subscription_required' => 'boolean',
            'user_plan_monthly' => 'decimal:2',
            'user_plan_yearly' => 'decimal:2',
            'business_plan_monthly' => 'decimal:2',
            'business_plan_yearly' => 'decimal:2',
            'free_trial_days' => 'integer',
            'payout_debtor_iban' => 'encrypted',
            'ai_enabled' => 'boolean',
            'anthropic_api_key' => 'encrypted',
            'openai_api_key' => 'encrypted',
            'ai_daily_limit' => 'integer',
            'anthropic_input_price' => 'decimal:4',
            'anthropic_output_price' => 'decimal:4',
            'openai_input_price' => 'decimal:4',
            'openai_output_price' => 'decimal:4',
        ];
    }

    public const array AI_PROVIDERS = ['anthropic', 'openai'];

    public function aiApiKey(string $provider): ?string
    {
        return match ($provider) {
            'anthropic' => $this->anthropic_api_key,
            'openai' => $this->openai_api_key,
            default => null,
        };
    }

    public function aiModel(string $provider): ?string
    {
        return match ($provider) {
            'anthropic' => $this->anthropic_model,
            'openai' => $this->openai_model,
            default => null,
        };
    }

    /**
     * What the provider charges for the chosen model, in USD per million input or output tokens.
     */
    public function aiPrice(string $provider, string $direction): ?string
    {
        return in_array($provider, self::AI_PROVIDERS, true) && in_array($direction, ['input', 'output'], true)
            ? $this->{"{$provider}_{$direction}_price"}
            : null;
    }

    /**
     * Whether users can chat with the card assistant: switched on, with a key and model for the chosen provider.
     */
    public function isAiReady(): bool
    {
        return $this->ai_enabled && filled($this->aiApiKey($this->ai_provider)) && filled($this->aiModel($this->ai_provider));
    }

    public static function current(): self
    {
        $settings = static::query()->firstOrCreate([]);

        // A row created just now does not hold the column defaults yet.
        return $settings->wasRecentlyCreated ? $settings->refresh() : $settings;
    }
}
