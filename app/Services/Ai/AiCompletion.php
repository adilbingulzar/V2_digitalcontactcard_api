<?php

namespace App\Services\Ai;

/**
 * A model's answer, already decoded from JSON, and what it cost in tokens and web searches.
 */
final readonly class AiCompletion
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $webSearches = 0,
    ) {}
}
