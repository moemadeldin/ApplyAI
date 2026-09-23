<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\Constants;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use JsonException;
use RuntimeException;
use Throwable;

final readonly class OpenAiCompatibleClient
{
    /**
     * @param  array<int, string>  $models
     */
    public function __construct(
        private array $models,
        private float $temperature,
        private string $apiKey,
        private string $baseUrl,
        private int $timeout,
        private string $namespace = 'openrouter',
        private string $label = 'OpenRouter'
    ) {}

    /**
     * @return array<mixed, mixed>
     */
    public function requestJson(string $systemPrompt, string $userPrompt): array
    {
        $content = $this->send($systemPrompt, $userPrompt, jsonMode: true);

        try {
            /** @var array<mixed, mixed> */
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException $jsonException) {
            $extracted = $this->extractJson($content);

            if ($extracted === null) {
                throw new RuntimeException('Provider returned malformed JSON.', $jsonException->getCode(), previous: $jsonException);
            }

            return $extracted;
        }
    }

    public function requestText(string $systemPrompt, string $userPrompt): string
    {
        return $this->send($systemPrompt, $userPrompt, jsonMode: false);
    }

    /**
     * @return array<int, string>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    private function send(string $systemPrompt, string $userPrompt, bool $jsonMode): string
    {
        $generation = $this->cacheGet(Constants::AI_CACHE_GENERATION_KEY, 0);
        $generation = is_int($generation) ? $generation : 0;

        $requestId = mb_substr(md5(uniqid((string) mt_rand(), true)), 0, 8);

        $lastException = null;

        foreach ($this->models as $model) {
            if ($this->isCircuitOpen($model)) {
                Log::debug($this->label.' circuit open, skipping model.', [
                    'request_id' => $requestId,
                    'model' => $model,
                ]);

                continue;
            }

            $cacheKey = $this->namespacePrefix().$generation.':'.md5($systemPrompt.'|'.$userPrompt.'|'.$model);

            $cached = $this->cacheGet($cacheKey);

            if (is_string($cached) && $cached !== '') {
                Log::debug($this->label.' cache hit.', [
                    'request_id' => $requestId,
                    'model' => $model,
                ]);

                return $cached;
            }

            Log::debug($this->label.' cache miss.', [
                'request_id' => $requestId,
                'model' => $model,
            ]);

            try {
                $content = $this->attempt($model, $systemPrompt, $userPrompt, $jsonMode, $requestId);

                $this->recordSuccess($model);

                $this->cachePut($cacheKey, $content);

                return $content;
            } catch (Throwable $e) {
                $lastException = $e;

                $this->recordFailure($model);

                Log::warning($this->label.' model failed, trying the next one.', [
                    'request_id' => $requestId,
                    'model' => $model,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::critical(sprintf('All %s models failed.', $this->label), [
            'request_id' => $requestId,
            'models' => $this->models,
            'last_exception' => $lastException instanceof Throwable ? $lastException::class : 'none',
            'message' => $lastException?->getMessage(),
        ]);

        throw new RuntimeException(
            'AI service is temporarily unavailable, please try again.',
            previous: $lastException
        );
    }

    private function attempt(
        string $model,
        string $systemPrompt,
        string $userPrompt,
        bool $jsonMode,
        string $requestId
    ): string {
        for ($attempt = 1; $attempt <= Constants::AI_OPENROUTER_MAX_ATTEMPTS_PER_MODEL; $attempt++) {
            try {
                $start = hrtime(true);
                $content = $this->postToProvider($model, $systemPrompt, $userPrompt, $jsonMode);
                $duration = (hrtime(true) - $start) / 1_000_000;

                if ($content === '') {
                    Log::warning($this->label.' returned an empty response.', [
                        'request_id' => $requestId,
                        'model' => $model,
                        'attempt' => $attempt,
                    ]);

                    throw new RuntimeException('Empty response from AI');
                }

                Log::info($this->label.' request succeeded.', [
                    'request_id' => $requestId,
                    'model' => $model,
                    'attempt' => $attempt,
                    'duration_ms' => round($duration, 1),
                ]);

                return $content;
            } catch (ConnectionException $e) {
                Log::warning($this->label.' connection error.', [
                    'request_id' => $requestId,
                    'model' => $model,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt < Constants::AI_OPENROUTER_MAX_ATTEMPTS_PER_MODEL) {
                    $this->waitBeforeConnectionRetry($attempt);

                    continue;
                }

                throw $e;
            }
        }

        throw new RuntimeException('AI service is temporarily unavailable, please try again.');
    }

    private function postToProvider(string $model, string $systemPrompt, string $userPrompt, bool $jsonMode): string
    {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $this->temperature,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout)
            ->post(mb_rtrim($this->baseUrl, '/').'/chat/completions', $payload);

        $status = $response->status();

        if ($status >= 400) {
            $error = $response->json('error.message');
            $message = is_string($error) && $error !== '' ? $error : 'API error';

            throw new RuntimeException(sprintf('%s request failed with an API error: %s', $this->label, $message));
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new RuntimeException($this->label.' returned an empty response.');
        }

        return $content;
    }

    /**
     * @return array<mixed, mixed>|null
     */
    private function extractJson(string $content): ?array
    {
        $content = mb_trim($content);

        if (preg_match('/```(?:json)?\s*(.+?)```/s', $content, $matches) === 1) {
            $content = mb_trim($matches[1]);
        }

        $start = mb_strpos($content, '{');

        if ($start === false) {
            return null;
        }

        $content = mb_substr($content, $start);

        $end = mb_strrpos($content, '}');

        if ($end === false) {
            return null;
        }

        $content = mb_substr($content, 0, $end + 1);

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }

    private function cacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (Throwable $throwable) {
            Log::warning('Cache read failed; proceeding without cache.', [
                'key' => $key,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return $default;
        }
    }

    private function cachePut(string $key, string $content): void
    {
        try {
            Cache::put($key, $content, Constants::AI_CACHE_TTL_SECONDS);
        } catch (Throwable $throwable) {
            Log::warning('Cache write failed; proceeding without caching.', [
                'key' => $key,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function namespacePrefix(): string
    {
        return match ($this->namespace) {
            'deepseek' => Constants::AI_CACHE_DEEPSEEK_PREFIX,
            default => Constants::AI_CACHE_OPENROUTER_PREFIX,
        };
    }

    private function waitBeforeConnectionRetry(int $attempt): void
    {
        Sleep::sleep(min(5 * $attempt, 20));
    }

    private function isCircuitOpen(string $model): bool
    {
        $state = $this->circuitState($model);

        if ($state === null || $state['failures'] < Constants::AI_CIRCUIT_BREAKER_FAILURE_THRESHOLD) {
            return false;
        }

        if (time() - $state['opened_at'] >= Constants::AI_CIRCUIT_BREAKER_COOLDOWN_SECONDS) {
            $this->resetCircuit($model);

            return false;
        }

        return true;
    }

    private function recordFailure(string $model): void
    {
        $state = $this->circuitState($model);
        $failures = ($state['failures'] ?? 0) + 1;

        try {
            Cache::put(
                Constants::AI_CIRCUIT_BREAKER_CACHE_PREFIX.$model,
                ['failures' => $failures, 'opened_at' => time()],
                Constants::AI_CIRCUIT_BREAKER_COOLDOWN_SECONDS
            );
        } catch (Throwable $throwable) {
            Log::warning('Circuit breaker write failed.', [
                'model' => $model,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function recordSuccess(string $model): void
    {
        $this->resetCircuit($model);
    }

    private function resetCircuit(string $model): void
    {
        try {
            Cache::forget(Constants::AI_CIRCUIT_BREAKER_CACHE_PREFIX.$model);
        } catch (Throwable $throwable) {
            Log::warning('Circuit breaker reset failed.', [
                'model' => $model,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @return array{failures: int, opened_at: int}|null
     */
    private function circuitState(string $model): ?array
    {
        $state = $this->cacheGet(Constants::AI_CIRCUIT_BREAKER_CACHE_PREFIX.$model);

        if (! is_array($state)) {
            return null;
        }

        $failures = $state['failures'] ?? null;
        $openedAt = $state['opened_at'] ?? null;

        if (! is_int($failures) || ! is_int($openedAt)) {
            return null;
        }

        return [
            'failures' => $failures,
            'opened_at' => $openedAt,
        ];
    }
}
