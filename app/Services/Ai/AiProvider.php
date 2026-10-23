<?php

namespace App\Services\Ai;

/**
 * An AI provider admins can choose for the card assistant (Anthropic or OpenAI).
 */
interface AiProvider
{
    /**
     * The models the API key can use, newest first.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws AiException
     */
    public function models(): array;

    /**
     * Asks the model for an answer in the shape of the JSON schema.
     *
     * Content is text, or a list of parts: ['type' => 'text', 'text' => …],
     * ['type' => 'image', 'media_type' => 'image/webp', 'data' => base64] or
     * ['type' => 'pdf', 'name' => …, 'data' => base64].
     *
     * @param  list<array{role: 'user'|'assistant', content: string|list<array<string, string>>}>  $messages
     * @param  array<string, mixed>  $schema
     *
     * @throws AiException
     */
    public function complete(string $system, array $messages, array $schema): AiCompletion;
}
