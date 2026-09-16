<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GeminiClient;
use App\Services\OpenAiCompatibleClient;
use Gemini\Exceptions\ErrorException;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Resources\GenerativeModel;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

function geminiClient(array $models): GeminiClient
{
    return new GeminiClient(
        models: $models,
        temperature: 0.3,
    );
}

function geminiJsonResponse(array $data = ['ok' => true]): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($data)]]],
        ]],
    ]);
}

function geminiTextResponse(string $text): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
        ]],
    ]);
}

function geminiError(int $code, string $message): ErrorException
{
    return new ErrorException([
        'code' => $code,
        'message' => $message,
        'status' => 'ERROR',
    ]);
}

function fakeGeminiLog(): AbstractLogger
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

function geminiLogMessages(AbstractLogger $logger, string $level): array
{
    return collect($logger->records)
        ->filter(fn (array $record): bool => $record['level'] === $level)
        ->map(fn (array $record): string => $record['message'])
        ->values()
        ->all();
}

test('returns decoded JSON for json requests', function (): void {
    Sleep::fake();
    Gemini::fake([geminiJsonResponse()]);

    $client = geminiClient(['gemini-model']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);
});

test('returns raw text for text requests', function (): void {
    Sleep::fake();
    Gemini::fake([geminiTextResponse('Hello world')]);

    $client = geminiClient(['gemini-model']);

    expect($client->requestText('system', 'prompt'))->toBe('Hello world');
});

test('fails over to the next model when the primary is rate limited', function (): void {
    Sleep::fake();
    $logger = fakeGeminiLog();
    Log::swap($logger);

    Gemini::fake([
        geminiError(429, 'rate limit'),
        geminiJsonResponse(),
    ]);

    $client = geminiClient(['model-a', 'model-b']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);
    Gemini::assertSent(GenerativeModel::class, 'model-a');
    Gemini::assertSent(GenerativeModel::class, 'model-b');
    expect(geminiLogMessages($logger, 'warning'))
        ->toContain('Gemini request failed with an API error.')
        ->toContain('Gemini model failed, trying the next one.');
});

test('retries transient API errors on the same model before failing over', function (): void {
    Sleep::fake();
    $logger = fakeGeminiLog();
    Log::swap($logger);

    Gemini::fake([
        geminiError(500, 'boom'),
        geminiError(500, 'boom'),
        geminiJsonResponse(),
    ]);

    $client = geminiClient(['model-a', 'model-b']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);
    Gemini::assertSent(GenerativeModel::class, 'model-a', 3);
    expect(geminiLogMessages($logger, 'warning'))->toContain('Gemini request failed with an API error.');
});

test('throws a clear exception when every model fails', function (): void {
    Sleep::fake();
    $logger = fakeGeminiLog();
    Log::swap($logger);

    Gemini::fake([
        geminiError(429, 'rate limit'),
        geminiError(429, 'rate limit'),
    ]);

    $client = geminiClient(['model-a', 'model-b']);

    expect(fn (): array => $client->requestJson('system', 'prompt'))
        ->toThrow(RuntimeException::class, 'AI service is temporarily unavailable, please try again.');

    expect(geminiLogMessages($logger, 'critical'))->toContain('All Gemini models failed.');
});

test('logs a warning and fails over when the provider returns an empty response', function (): void {
    Sleep::fake();
    $logger = fakeGeminiLog();
    Log::swap($logger);

    Gemini::fake([
        geminiTextResponse(''),
        geminiJsonResponse(),
    ]);

    $client = geminiClient(['model-a', 'model-b']);

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);
    expect(geminiLogMessages($logger, 'warning'))->toContain('Gemini returned an empty response.');
});

test('caches the result and skips the network on repeat calls', function (): void {
    Sleep::fake();
    $logger = fakeGeminiLog();
    Log::swap($logger);

    Gemini::fake([geminiJsonResponse()]);

    $client = geminiClient(['model-a']);

    $first = $client->requestJson('system', 'prompt');
    $second = $client->requestJson('system', 'prompt');

    expect($second)->toBe($first);
    Gemini::assertSent(GenerativeModel::class, 'model-a', 1);
    expect(geminiLogMessages($logger, 'debug'))->toContain('Gemini cache miss.')
        ->toContain('Gemini cache hit.');
});

test('falls back to OpenRouter when every Gemini model fails', function (): void {
    Sleep::fake();
    $logger = fakeGeminiLog();
    Log::swap($logger);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => json_encode(['ok' => true])],
            ]],
        ]),
    ]);

    Gemini::fake([
        geminiError(429, 'rate limit'),
        geminiError(429, 'rate limit'),
    ]);

    $fallback = new OpenAiCompatibleClient(
        models: ['nvidia/nemotron-3-ultra-550b-a55b:free'],
        temperature: 0.3,
        apiKey: 'test-key',
        baseUrl: 'https://openrouter.ai/api/v1',
        timeout: 30,
    );

    $client = new GeminiClient(
        models: ['model-a', 'model-b'],
        temperature: 0.3,
        fallbackClient: $fallback,
    );

    expect($client->requestJson('system', 'prompt'))->toBe(['ok' => true]);
    Gemini::assertSent(GenerativeModel::class, 'model-a');
    Gemini::assertSent(GenerativeModel::class, 'model-b');
    Http::assertSentCount(1);
});

test('throws when Gemini and the OpenRouter fallback both fail', function (): void {
    Sleep::fake();
    $logger = fakeGeminiLog();
    Log::swap($logger);

    Http::fake([
        'openrouter.ai/*' => Http::response(['error' => ['message' => 'rate limit']], 429),
    ]);

    Gemini::fake([
        geminiError(429, 'rate limit'),
        geminiError(429, 'rate limit'),
    ]);

    $fallback = new OpenAiCompatibleClient(
        models: ['nvidia/nemotron-3-ultra-550b-a55b:free'],
        temperature: 0.3,
        apiKey: 'test-key',
        baseUrl: 'https://openrouter.ai/api/v1',
        timeout: 30,
    );

    $client = new GeminiClient(
        models: ['model-a', 'model-b'],
        temperature: 0.3,
        fallbackClient: $fallback,
    );

    expect(fn (): array => $client->requestJson('system', 'prompt'))
        ->toThrow(RuntimeException::class, 'AI service is temporarily unavailable, please try again.');

    expect(geminiLogMessages($logger, 'critical'))
        ->toContain('Gemini models failed and the fallback provider also failed.');
});
