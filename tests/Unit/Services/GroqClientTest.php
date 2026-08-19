<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GroqClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

function groqClient(array $models): GroqClient
{
    return new GroqClient(
        models: $models,
        temperature: 0.3,
        apiKey: 'key',
        apiChat: 'https://api.groq.com/openai/v1/chat/completions',
        timeout: 30,
    );
}

function groqOkResponse(): array
{
    return [
        'choices' => [[
            'message' => ['content' => json_encode(['ok' => true])],
        ]],
    ];
}

test('fails over to the next model when the primary is rate limited', function (): void {
    Sleep::fake();

    Http::fake([
        '*' => function ($request) {
            if (data_get($request->data(), 'model') === 'primary-model') {
                return Http::response(['error' => ['message' => 'rate limit']], Response::HTTP_TOO_MANY_REQUESTS);
            }

            return Http::response(groqOkResponse(), Response::HTTP_OK);
        },
    ]);

    $client = groqClient(['primary-model', 'backup-model']);

    $result = $client->requestJson('system', 'prompt');

    expect($result)->toBe(['ok' => true]);
    Http::assertSentCount(2);
});

test('throws a clear exception when every model is rate limited', function (): void {
    Sleep::fake();

    Http::fake([
        '*' => Http::response(['error' => ['message' => 'rate limit']], Response::HTTP_TOO_MANY_REQUESTS),
    ]);

    $client = groqClient(['model-a', 'model-b']);

    $client->requestJson('system', 'prompt');
})->throws(RuntimeException::class, 'AI service is temporarily unavailable, please try again.');

test('retries transient server errors on the same model before failing over', function (): void {
    Sleep::fake();

    $attempts = 0;

    Http::fake([
        '*' => function () use (&$attempts) {
            $attempts++;

            if ($attempts < 3) {
                return Http::response(['error' => ['message' => 'boom']], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return Http::response(groqOkResponse(), Response::HTTP_OK);
        },
    ]);

    $client = groqClient(['primary-model', 'backup-model']);

    $result = $client->requestJson('system', 'prompt');

    expect($result)->toBe(['ok' => true])
        ->and($attempts)->toBe(3);
    Http::assertSentCount(3);
});

test('retries connection errors on the same model before failing over', function (): void {
    Sleep::fake();

    $attempts = 0;

    Http::fake([
        '*' => function () use (&$attempts) {
            $attempts++;

            throw_if($attempts < 3, ConnectionException::class, 'timeout');

            return Http::response(groqOkResponse(), Response::HTTP_OK);
        },
    ]);

    $client = groqClient(['primary-model', 'backup-model']);

    $result = $client->requestJson('system', 'prompt');

    expect($result)->toBe(['ok' => true])
        ->and($attempts)->toBe(3);
});

test('caches the result per model and skips the network on repeat calls', function (): void {
    Sleep::fake();

    $attempts = 0;

    Http::fake([
        '*' => function () use (&$attempts) {
            $attempts++;

            return Http::response(groqOkResponse(), Response::HTTP_OK);
        },
    ]);

    $client = groqClient(['primary-model', 'backup-model']);

    $first = $client->requestJson('system', 'prompt');
    $second = $client->requestJson('system', 'prompt');

    expect($second)->toBe($first)
        ->and($attempts)->toBe(1);
    Http::assertSentCount(1);
});

test('the container resolves the client with the configured model chain', function (): void {
    config()->set('ai_services.models', ['model-a', 'model-b']);

    $client = resolve(GroqClient::class);

    expect($client)->toBeInstanceOf(GroqClient::class);
});
