<?php

namespace Tests\Feature\Services\Ai;

use App\Services\Ai\AiException;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    public function test_lists_only_chat_models_newest_first(): void
    {
        Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [
            ['id' => 'gpt-4o', 'created' => 100],
            ['id' => 'text-embedding-3-large', 'created' => 300],
            ['id' => 'gpt-5.1', 'created' => 200],
            ['id' => 'gpt-4o-realtime-preview', 'created' => 250],
            ['id' => 'o4-mini', 'created' => 150],
        ]])]);

        $models = (new OpenAiProvider('sk-test'))->models();

        $this->assertSame(['gpt-5.1', 'o4-mini', 'gpt-4o'], array_column($models, 'id'));
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-test'));
    }

    public function test_asks_for_json_in_the_schema_and_decodes_it(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-5.1-2026-09-01',
            'choices' => [['message' => ['content' => '{"reply":"Hi"}', 'refusal' => null], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 7],
        ])]);
        $schema = ['type' => 'object', 'properties' => ['reply' => ['type' => 'string']], 'required' => ['reply'], 'additionalProperties' => false];

        $completion = (new OpenAiProvider('sk-test', 'gpt-5.1'))->complete('Be brief.', [['role' => 'user', 'content' => 'Hello']], $schema);

        $this->assertSame(['reply' => 'Hi'], $completion->data);
        $this->assertSame('gpt-5.1-2026-09-01', $completion->model);
        $this->assertSame([50, 7], [$completion->inputTokens, $completion->outputTokens]);
        Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-5.1'
            && $request['messages'] === [['role' => 'system', 'content' => 'Be brief.'], ['role' => 'user', 'content' => 'Hello']]
            && $request['response_format']['json_schema']['strict'] === true
            && $request['response_format']['json_schema']['schema'] === $schema);
    }

    public function test_explains_a_rejected_key(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key']], 401)]);

        $this->expectException(AiException::class);
        $this->expectExceptionMessage('OpenAI did not accept the API key.');

        (new OpenAiProvider('sk-wrong'))->models();
    }

    public function test_sends_pictures_and_pdfs_as_content_parts(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"reply":"Nice card"}'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 9],
        ])]);

        (new OpenAiProvider('sk-test', 'gpt-6.1-sol'))->complete('Be brief.', [['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => 'Like this'],
            ['type' => 'image', 'media_type' => 'image/webp', 'data' => 'AAAA'],
            ['type' => 'pdf', 'name' => 'brand.pdf', 'data' => 'BBBB'],
        ]]], ['type' => 'object']);

        Http::assertSent(fn (Request $request) => $request['messages'][1]['content'] === [
            ['type' => 'text', 'text' => 'Like this'],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/webp;base64,AAAA']],
            ['type' => 'file', 'file' => ['filename' => 'brand.pdf', 'file_data' => 'data:application/pdf;base64,BBBB']],
        ]);
    }

    public function test_searches_the_web_through_the_responses_api_and_counts_only_searches(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'model' => 'gpt-5.1-2026-09-01',
            'output' => [
                ['type' => 'web_search_call', 'action' => ['type' => 'search', 'query' => 'KFC Pakistan']],
                ['type' => 'web_search_call', 'action' => ['type' => 'open_page', 'url' => 'https://www.kfcpakistan.com/']],
                ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Phone +92-21-35877976 (https://www.kfcpakistan.com/)']]],
            ],
            'usage' => ['input_tokens' => 900, 'output_tokens' => 60],
        ])]);

        $found = (new OpenAiProvider('sk-test', 'gpt-5.1'))->searchWeb('Find business facts.', 'KFC Pakistan contact');

        $this->assertSame(['text' => 'Phone +92-21-35877976 (https://www.kfcpakistan.com/)'], $found->data);
        $this->assertSame([900, 60, 1], [$found->inputTokens, $found->outputTokens, $found->webSearches]);
        Http::assertSent(fn (Request $request) => $request['tools'] === [['type' => 'web_search']]
            && $request['instructions'] === 'Find business facts.'
            && $request['input'] === 'KFC Pakistan contact');
    }
}
