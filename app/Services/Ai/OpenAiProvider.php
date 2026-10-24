<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI's chat models, through their REST API (there is no official PHP SDK).
 */
class OpenAiProvider implements AiProvider
{
    private const string BASE_URL = 'https://api.openai.com/v1';

    /**
     * Model families that do not chat: speech, images, embeddings and the like.
     */
    private const string NOT_FOR_CHAT = '/(audio|realtime|tts|transcribe|whisper|dall-e|image|embedding|moderation|search|instruct|codex|computer-use)/i';

    public function __construct(private string $apiKey, private ?string $model = null) {}

    public function models(): array
    {
        $models = collect($this->send(fn (PendingRequest $request) => $request->get('models'))->json('data', []))
            ->filter(fn (mixed $model): bool => is_array($model) && is_string($model['id'] ?? null))
            ->filter(fn (array $model): bool => preg_match('/^(gpt-|o\d|chatgpt-)/i', $model['id']) === 1 && preg_match(self::NOT_FOR_CHAT, $model['id']) === 0)
            ->sortByDesc(fn (array $model): int => (int) ($model['created'] ?? 0))
            ->map(fn (array $model): array => ['id' => $model['id'], 'name' => $model['id']]);

        return $models->values()->all();
    }

    public function complete(string $system, array $messages, array $schema): AiCompletion
    {
        if (blank($this->model)) {
            throw new AiException('Choose a model first.');
        }

        $response = $this->send(fn (PendingRequest $request) => $request->post('chat/completions', [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ...array_map(fn (array $message): array => ['role' => $message['role'], 'content' => self::content($message['content'])], $messages),
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'answer', 'strict' => true, 'schema' => $schema],
            ],
            'max_completion_tokens' => 16000,
        ]));

        $usage = new AiCompletion(
            [],
            (string) ($response->json('model') ?? $this->model),
            (int) $response->json('usage.prompt_tokens', 0),
            (int) $response->json('usage.completion_tokens', 0),
        );
        if (filled($response->json('choices.0.message.refusal'))) {
            throw new AiException('The model declined to answer this message.', $usage);
        }
        if ($response->json('choices.0.finish_reason') === 'length') {
            throw new AiException('The answer was too long.', $usage);
        }

        $data = json_decode((string) $response->json('choices.0.message.content'), true);
        if (! is_array($data)) {
            throw new AiException('The model did not answer in the expected format.', $usage);
        }

        return new AiCompletion($data, $usage->model, $usage->inputTokens, $usage->outputTokens);
    }

    /**
     * Web search only exists in the Responses API, so this one call goes there.
     */
    public function searchWeb(string $instructions, string $query): AiCompletion
    {
        if (blank($this->model)) {
            throw new AiException('Choose a model first.');
        }

        $response = $this->send(fn (PendingRequest $request) => $request->post('responses', [
            'model' => $this->model,
            'instructions' => $instructions,
            'input' => $query,
            'tools' => [['type' => 'web_search']],
            'max_output_tokens' => 4000,
        ]));

        $text = '';
        $searches = 0;
        foreach ($response->json('output', []) as $item) {
            // Only searches are charged; opening a found page is not.
            if (($item['type'] ?? null) === 'web_search_call' && in_array($item['action']['type'] ?? 'search', ['search'], true)) {
                $searches++;
            }
            if (($item['type'] ?? null) === 'message') {
                foreach ($item['content'] ?? [] as $part) {
                    if (($part['type'] ?? null) === 'output_text') {
                        $text .= $part['text'];
                    }
                }
            }
        }

        return new AiCompletion(
            ['text' => trim($text)],
            (string) ($response->json('model') ?? $this->model),
            (int) $response->json('usage.input_tokens', 0),
            (int) $response->json('usage.output_tokens', 0),
            $searches,
        );
    }

    /**
     * @param  string|list<array<string, string>>  $content
     * @return string|list<array<string, mixed>>
     */
    private static function content(string|array $content): string|array
    {
        return is_string($content) ? $content : array_map(fn (array $part): array => match ($part['type']) {
            'image' => ['type' => 'image_url', 'image_url' => ['url' => "data:{$part['media_type']};base64,{$part['data']}"]],
            'pdf' => ['type' => 'file', 'file' => ['filename' => $part['name'], 'file_data' => "data:application/pdf;base64,{$part['data']}"]],
            default => ['type' => 'text', 'text' => $part['text']],
        }, $content);
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws AiException
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request(Http::baseUrl(self::BASE_URL)->withToken($this->apiKey)->acceptJson()->connectTimeout(10)->timeout(60));
        } catch (ConnectionException) {
            throw new AiException('OpenAI could not be reached.');
        }

        if ($response->successful()) {
            return $response;
        }

        throw new AiException(match ($response->status()) {
            401, 403 => 'OpenAI did not accept the API key.',
            404 => 'OpenAI does not know this model.',
            429 => 'OpenAI is limiting requests (rate limit or no credit left). Try again later.',
            default => 'OpenAI returned an error: '.($response->json('error.message') ?? 'HTTP '.$response->status()),
        });
    }
}
