<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\OpenAiCompatibleClient;
use App\Utilities\Constants;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

function openRouterClient(array $models): OpenAiCompatibleClient
{
    return new OpenAiCompatibleClient(
        models: $models,
        temperature: 0.3,
        apiKey: 'test-key',
        baseUrl: 'https://openrouter.ai/api/v1',
        timeout: 30,
    );
}

function openRouterHttpResponse(string $content): array
{
    return [
        'choices' => [[
            'message' => ['role' => 'assistant', 'content' => $content],
        ]],
    ];
}

function fakeOpenRouterLog(): AbstractLogger
{
    return new class extends AbstractLogger
    {
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = [
                'level' => $level,
                'message' => (string) $message,
                'context' => $context,
            ];
        }
    };
}

function openRouterLogMessages(AbstractLogger $logger, string $level): array
{
    return collect($logger->records)
        ->filter(fn (array $record): bool => $record['level'] === $level)
        ->map(fn (array $record): string => $record['message'])
        ->values()
        ->all();
}

beforeEach(function (): void {
    Cache::flush();
});

test('returns decoded JSON for json requests', function (): void {
    Sleep::fake();
    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterHttpResponse(json_encode(['ok' => true]))),
    ]);

    $client = openRouterClient(['nvidia/nemotron-3-ultra-550b-a55b:free']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);
});

test('returns raw text for text requests', function (): void {
    Sleep::fake();
    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterHttpResponse('Hello world')),
    ]);

    $client = openRouterClient(['nvidia/nemotron-3-ultra-550b-a55b:free']);

    expect($client->requestText('system', 'prompt'))->toBe('Hello world');
});

test('sends the expected OpenAI-compatible payload', function (): void {
    Sleep::fake();
    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterHttpResponse(json_encode(['ok' => true]))),
    ]);

    $client = openRouterClient(['nvidia/nemotron-3-ultra-550b-a55b:free']);

    $client->requestJson('system prompt', 'user prompt');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $body['model'] === 'nvidia/nemotron-3-ultra-550b-a55b:free'
            && $body['messages'][0] === ['role' => 'system', 'content' => 'system prompt']
            && $body['messages'][1] === ['role' => 'user', 'content' => 'user prompt']
            && $body['response_format'] === ['type' => 'json_object']
            && $request->hasHeader('Authorization', 'Bearer test-key');
    });
});

test('fails over to the next model when the primary returns an API error', function (): void {
    Sleep::fake();
    $logger = fakeOpenRouterLog();
    Log::swap($logger);

    Http::fake([
        'openrouter.ai/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limit']], 429)
            ->push(openRouterHttpResponse(json_encode(['ok' => true]))),
    ]);

    $client = openRouterClient(['model-a', 'model-b']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);
    Http::assertSentCount(2);
    expect(openRouterLogMessages($logger, 'warning'))
        ->toContain('OpenRouter model failed, trying the next one.');
});

test('retries transient connection errors on the same model before failing over', function (): void {
    Sleep::fake();
    $logger = fakeOpenRouterLog();
    Log::swap($logger);

    Http::fake([
        'openrouter.ai/*' => function ($request) {
            static $calls = 0;
            $calls++;

            if ($calls < 3) {
                throw new ConnectException('Transient connection failure', $request->toPsrRequest());
            }

            return Http::response(openRouterHttpResponse(json_encode(['ok' => true])));
        },
    ]);

    $client = openRouterClient(['model-a', 'model-b']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);
    Http::assertSentCount(3);
    expect(openRouterLogMessages($logger, 'warning'))->toContain('OpenRouter connection error.');
});

test('throws a clear exception when every model fails', function (): void {
    Sleep::fake();
    $logger = fakeOpenRouterLog();
    Log::swap($logger);

    Http::fake([
        'openrouter.ai/*' => Http::response(['error' => ['message' => 'rate limit']], 429),
    ]);

    $client = openRouterClient(['model-a', 'model-b']);

    expect(fn (): array => $client->requestJson('system', 'prompt'))
        ->toThrow(RuntimeException::class, 'AI service is temporarily unavailable, please try again.');

    expect(openRouterLogMessages($logger, 'critical'))->toContain('All OpenRouter models failed.');
});

test('caches the result and skips the network on repeat calls', function (): void {
    Sleep::fake();
    $logger = fakeOpenRouterLog();
    Log::swap($logger);

    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterHttpResponse(json_encode(['ok' => true]))),
    ]);

    $client = openRouterClient(['nvidia/nemotron-3-ultra-550b-a55b:free']);

    $first = $client->requestJson('system', 'prompt');
    $second = $client->requestJson('system', 'prompt');

    expect($second)->toBe($first);
    Http::assertSentCount(1);
    expect(openRouterLogMessages($logger, 'debug'))->toContain('OpenRouter cache miss.')
        ->toContain('OpenRouter cache hit.');
});

test('decodes JSON content wrapped in markdown fences', function (): void {
    Sleep::fake();
    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterHttpResponse("```json\n{\"ok\": true}\n```")),
    ]);

    $client = openRouterClient(['nvidia/nemotron-3-ultra-550b-a55b:free']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);
});

test('decodes JSON content surrounded by prose', function (): void {
    Sleep::fake();
    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterHttpResponse(
            "Here is the result you asked for:\n{\"ok\": true, \"note\": \"done\"}\nHope that helps."
        )),
    ]);

    $client = openRouterClient(['nvidia/nemotron-3-ultra-550b-a55b:free']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true, 'note' => 'done']);
});

test('throws a clear exception when the response is not valid JSON', function (): void {
    Sleep::fake();
    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterHttpResponse('No JSON here at all')),
    ]);

    $client = openRouterClient(['nvidia/nemotron-3-ultra-550b-a55b:free']);

    expect(fn (): array => $client->requestJson('system', 'prompt'))
        ->toThrow(RuntimeException::class, 'Provider returned malformed JSON.');
});

test('skips a model while the circuit breaker is open', function (): void {
    Sleep::fake();
    $logger = fakeOpenRouterLog();
    Log::swap($logger);

    Cache::put(Constants::AI_CIRCUIT_BREAKER_CACHE_PREFIX.'model-a', [
        'failures' => Constants::AI_CIRCUIT_BREAKER_FAILURE_THRESHOLD,
        'opened_at' => time(),
    ], Constants::AI_CIRCUIT_BREAKER_COOLDOWN_SECONDS);

    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterHttpResponse(json_encode(['ok' => true]))),
    ]);

    $client = openRouterClient(['model-a']);

    expect(fn (): array => $client->requestJson('system', 'prompt'))
        ->toThrow(RuntimeException::class, 'AI service is temporarily unavailable, please try again.');

    Http::assertNothingSent();
});

test('records failures toward the circuit breaker threshold', function (): void {
    Sleep::fake();
    $logger = fakeOpenRouterLog();
    Log::swap($logger);

    Http::fake([
        'openrouter.ai/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limit']], 429)
            ->push(openRouterHttpResponse(json_encode(['ok' => true]))),
    ]);

    $client = openRouterClient(['model-a', 'model-b']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);

    $state = Cache::get(Constants::AI_CIRCUIT_BREAKER_CACHE_PREFIX.'model-a');

    expect($state)->not->toBeNull()
        ->and($state['failures'])->toBeGreaterThanOrEqual(1);
});

test('resets the circuit breaker state after a successful call', function (): void {
    Sleep::fake();

    Cache::put(Constants::AI_CIRCUIT_BREAKER_CACHE_PREFIX.'model-a', [
        'failures' => 2,
        'opened_at' => time(),
    ], Constants::AI_CIRCUIT_BREAKER_COOLDOWN_SECONDS);

    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterHttpResponse(json_encode(['ok' => true]))),
    ]);

    $client = openRouterClient(['model-a']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);

    expect(Cache::get(Constants::AI_CIRCUIT_BREAKER_CACHE_PREFIX.'model-a'))->toBeNull();
});
