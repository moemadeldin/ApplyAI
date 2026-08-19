<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

final readonly class GroqClient
{
    private const int MAX_ATTEMPTS_PER_MODEL = 3;

    /**
     * @param  array<int, string>  $models
     */
    public function __construct(
        private array $models,
        private float $temperature,
        private string $apiKey,
        private string $apiChat,
        private int $timeout
    ) {}

    /**
     * @return array<mixed, mixed>
     */
    public function requestJson(string $systemPrompt, string $userPrompt): array
    {
        $content = $this->send($systemPrompt, $userPrompt, jsonMode: true);

        /** @var array<mixed, mixed> */
        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    public function requestText(string $systemPrompt, string $userPrompt): string
    {
        return $this->send($systemPrompt, $userPrompt, jsonMode: false);
    }

    private function send(string $systemPrompt, string $userPrompt, bool $jsonMode): string
    {
        $generation = Cache::get('ai:generation', 0);
        $generation = is_int($generation) ? $generation : 0;

        $lastException = null;

        foreach ($this->models as $model) {
            $cacheKey = 'ai:'.$generation.':'.md5($systemPrompt.'|'.$userPrompt.'|'.$model);

            $cached = Cache::get($cacheKey);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            try {
                $content = $this->attempt($model, $systemPrompt, $userPrompt, $jsonMode);

                Cache::put($cacheKey, $content, 86400 * 30);

                return $content;
            } catch (Throwable $e) {
                $lastException = $e;
            }
        }

        throw new RuntimeException(
            'AI service is temporarily unavailable, please try again.',
            previous: $lastException
        );
    }

    private function attempt(string $model, string $systemPrompt, string $userPrompt, bool $jsonMode): string
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS_PER_MODEL; $attempt++) {
            try {
                $response = $this->postToProvider($model, $systemPrompt, $userPrompt, $jsonMode);

                /** @var string|null $content */
                $content = $response->json('choices.0.message.content');

                throw_if($content === null, RuntimeException::class, 'Empty response from AI');

                return $content;
            } catch (RequestException $e) {
                throw_if($e->response->status() === 429, $e);

                if ($attempt < self::MAX_ATTEMPTS_PER_MODEL && $this->isRetryable($e->response->status())) {
                    $this->waitBeforeRetry($e, $attempt);

                    continue;
                }

                throw $e;
            } catch (ConnectionException $e) {
                if ($attempt < self::MAX_ATTEMPTS_PER_MODEL) {
                    $this->waitBeforeRetry(null, $attempt);

                    continue;
                }

                throw $e;
            }
        }

        throw new RuntimeException('AI service is temporarily unavailable, please try again.');
    }

    private function postToProvider(string $model, string $systemPrompt, string $userPrompt, bool $jsonMode): Response
    {
        $body = [
            'model' => $model,
            'temperature' => $this->temperature,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ];

        if ($jsonMode) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        return Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->post($this->apiChat, $body)
            ->throw();
    }

    private function isRetryable(?int $status): bool
    {
        return in_array($status, [408, 500, 502, 503, 504], true);
    }

    private function waitBeforeRetry(?RequestException $e, int $attempt): void
    {
        $retryAfter = $e?->response?->header('Retry-After');
        $retryAfter = is_numeric($retryAfter) ? (int) $retryAfter : 0;

        $delay = $retryAfter > 0 ? min($retryAfter, 15) : min(2 ** $attempt, 8);

        Sleep::sleep($delay);
    }
}
