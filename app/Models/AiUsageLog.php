<?php

namespace App\Models;

use App\Services\Ai\AiCompletion;
use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request to an AI provider: who caused it, the tokens and web searches it used
 * and its cost in USD, worked out with the prices admins set for the provider at that moment.
 */
#[Fillable(['user_id', 'feature', 'provider', 'model', 'input_tokens', 'output_tokens', 'web_searches', 'input_price', 'output_price', 'search_price', 'cost', 'succeeded'])]
class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory;

    public const string FEATURE_CARD_ASSISTANT = 'card_assistant';

    public const string FEATURE_CONNECTION_TEST = 'connection_test';

    public const string FEATURE_CARD_IMPORT = 'card_import';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'web_searches' => 'integer',
            'input_price' => 'decimal:4',
            'output_price' => 'decimal:4',
            'search_price' => 'decimal:4',
            'cost' => 'decimal:6',
            'succeeded' => 'boolean',
        ];
    }

    public static function record(?User $user, string $feature, string $provider, AiCompletion $completion, bool $succeeded = true): self
    {
        $settings = AppSetting::current();
        $inputPrice = $settings->aiPrice($provider, 'input');
        $outputPrice = $settings->aiPrice($provider, 'output');
        $searchPrice = $completion->webSearches > 0 ? $settings->aiPrice($provider, 'search') : null;
        $cost = $inputPrice === null || $outputPrice === null || ($completion->webSearches > 0 && $searchPrice === null)
            ? null
            : ($completion->inputTokens * (float) $inputPrice + $completion->outputTokens * (float) $outputPrice) / 1_000_000
                + $completion->webSearches * (float) $searchPrice / 1000;

        return self::create([
            'user_id' => $user?->id,
            'feature' => $feature,
            'provider' => $provider,
            'model' => $completion->model,
            'input_tokens' => $completion->inputTokens,
            'output_tokens' => $completion->outputTokens,
            'web_searches' => $completion->webSearches,
            'input_price' => $inputPrice,
            'output_price' => $outputPrice,
            'search_price' => $searchPrice,
            'cost' => $cost === null ? null : round($cost, 6),
            'succeeded' => $succeeded,
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
