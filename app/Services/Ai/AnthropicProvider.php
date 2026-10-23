<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Models\ModelInfo;
use Throwable;

/**
 * Claude, through Anthropic's official PHP SDK.
 */
class AnthropicProvider implements AiProvider
{
    public function __construct(private string $apiKey, private ?string $model = null) {}

    public function models(): array
    {
        $page = $this->call(fn (Client $client) => $client->models->list(limit: 100));

        return array_values(array_map(
            fn (ModelInfo $model): array => ['id' => $model->id, 'name' => $model->displayName],
            $page->getItems(),
        ));
    }

    public function complete(string $system, array $messages, array $schema): AiCompletion
    {
        if (blank($this->model)) {
            throw new AiException('Choose a model first.');
        }

        $message = $this->call(fn (Client $client) => $client->messages->create(
            maxTokens: 16000,
            messages: array_map(fn (array $message): array => ['role' => $message['role'], 'content' => self::content($message['content'])], $messages),
            model: $this->model,
            system: $system,
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        ));

        $usage = new AiCompletion([], $message->model, $message->usage->inputTokens, $message->usage->outputTokens);
        if ($message->stopReason === 'refusal') {
            throw new AiException('The model declined to answer this message.', $usage);
        }
        if ($message->stopReason === 'max_tokens') {
            throw new AiException('The answer was too long.', $usage);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new AiException('The model did not answer in the expected format.', $usage);
        }

        return new AiCompletion($data, $usage->model, $usage->inputTokens, $usage->outputTokens);
    }

    /**
     * @param  string|list<array<string, string>>  $content
     * @return string|list<array<string, mixed>>
     */
    private static function content(string|array $content): string|array
    {
        return is_string($content) ? $content : array_map(fn (array $part): array => match ($part['type']) {
            'image' => ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $part['media_type'], 'data' => $part['data']]],
            'pdf' => ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $part['data']]],
            default => ['type' => 'text', 'text' => $part['text']],
        }, $content);
    }

    /**
     * @template T
     *
     * @param  callable(Client): T  $request
     * @return T
     *
     * @throws AiException
     */
    private function call(callable $request): mixed
    {
        $client = new Client(apiKey: $this->apiKey, requestOptions: ['timeout' => 60, 'maxRetries' => 1]);

        try {
            return $request($client);
        } catch (AuthenticationException|PermissionDeniedException) {
            throw new AiException('Anthropic did not accept the API key.');
        } catch (NotFoundException) {
            throw new AiException('Anthropic does not know this model.');
        } catch (RateLimitException) {
            throw new AiException('Anthropic is limiting requests (rate limit or no credit left). Try again later.');
        } catch (APIConnectionException) {
            throw new AiException('Anthropic could not be reached.');
        } catch (APIStatusException $exception) {
            throw new AiException('Anthropic returned an error: '.$exception->getMessage());
        } catch (APIException|Throwable $exception) {
            report($exception);

            throw new AiException('Anthropic could not answer.');
        }
    }
}
