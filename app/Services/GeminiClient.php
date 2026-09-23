<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\Constants;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Gemini\Exceptions\ErrorException;
use Gemini\Exceptions\TransporterException;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

final readonly class GeminiClient
{
    /**
     * @param  array<int, string>  $models
     */
    public function __construct(
        private array $models,
        private float $temperature,
        private ?OpenAiCompatibleClient $fallbackClient = null
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
                Log::debug('Gemini circuit open, skipping model.', [
                    'request_id' => $requestId,
                    'model' => $model,
                ]);

                continue;
            }

            $cacheKey = Constants::AI_CACHE_GEMINI_PREFIX.$generation.':'.md5($systemPrompt.'|'.$userPrompt.'|'.$model);

            $cached = $this->cacheGet($cacheKey);

            if (is_string($cached) && $cached !== '') {
                Log::debug('Gemini cache hit.', [
                    'request_id' => $requestId,
                    'model' => $model,
                ]);

                return $cached;
            }

            Log::debug('Gemini cache miss.', [
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

                Log::warning('Gemini model failed, trying the next one.', [
                    'request_id' => $requestId,
                    'model' => $model,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($this->fallbackClient instanceof OpenAiCompatibleClient) {
            try {
                $fallbackContent = $jsonMode
                    ? $this->fallbackClient->requestJson($systemPrompt, $userPrompt)
                    : $this->fallbackClient->requestText($systemPrompt, $userPrompt);

                $content = is_array($fallbackContent)
                    ? json_encode($fallbackContent, flags: JSON_THROW_ON_ERROR)
                    : $fallbackContent;

                $this->cachePut(
                    Constants::AI_CACHE_GEMINI_PREFIX.$generation.':'.md5($systemPrompt.'|'.$userPrompt.'|'.Constants::AI_CACHE_FALLBACK_KEY),
                    $content
                );

                return $content;
            } catch (Throwable $e) {
                $lastException = $e;

                Log::critical('Gemini models failed and the fallback provider also failed.', [
                    'request_id' => $requestId,
                    'provider' => OpenAiCompatibleClient::class,
                    'last_exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::critical('All Gemini models failed.', [
            'request_id' => $requestId,
            'models' => $this->models,
            'last_exception' => $lastException instanceof Throwable ? $lastException::class : 'none',
            'message' => $lastException?->getMessage(),
            'trace' => $lastException?->getTraceAsString(),
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
        for ($attempt = 1; $attempt <= Constants::AI_GEMINI_MAX_ATTEMPTS_PER_MODEL; $attempt++) {
            try {
                $start = hrtime(true);
                $response = $this->postToProvider($model, $systemPrompt, $userPrompt, $jsonMode);
                $duration = (hrtime(true) - $start) / 1_000_000;

                $content = $response->text();

                if ($content === '') {
                    Log::warning('Gemini returned an empty response.', [
                        'request_id' => $requestId,
                        'model' => $model,
                        'attempt' => $attempt,
                    ]);

                    throw new RuntimeException('Empty response from AI');
                }

                Log::info('Gemini request succeeded.', [
                    'request_id' => $requestId,
                    'model' => $model,
                    'attempt' => $attempt,
                    'duration_ms' => round($duration, 1),
                ]);

                return $content;
            } catch (ErrorException $e) {
                Log::warning('Gemini request failed with an API error.', [
                    'request_id' => $requestId,
                    'model' => $model,
                    'attempt' => $attempt,
                    'status' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]);

                if ($e->getErrorCode() !== 429 && $attempt < Constants::AI_GEMINI_MAX_ATTEMPTS_PER_MODEL && $this->isRetryable($e)) {
                    $this->waitBeforeRetry($attempt);

                    continue;
                }

                throw $e;
            } catch (TransporterException $e) {
                Log::warning('Gemini connection error.', [
                    'request_id' => $requestId,
                    'model' => $model,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt < Constants::AI_GEMINI_MAX_ATTEMPTS_PER_MODEL) {
                    $this->waitBeforeConnectionRetry($attempt);

                    continue;
                }

                throw $e;
            }
        }

        throw new RuntimeException('AI service is temporarily unavailable, please try again.');
    }

    private function postToProvider(string $model, string $systemPrompt, string $userPrompt, bool $jsonMode): GenerateContentResponse
    {
        return Gemini::generativeModel(model: $model)
            ->withSystemInstruction(Content::parse($systemPrompt))
            ->withGenerationConfig(new GenerationConfig(
                temperature: $this->temperature,
                responseMimeType: $jsonMode ? ResponseMimeType::APPLICATION_JSON : ResponseMimeType::TEXT_PLAIN,
            ))
            ->generateContent($userPrompt);
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

    private function isRetryable(ErrorException $e): bool
    {
        return in_array($e->getErrorCode(), [408, 500, 502, 503, 504], true);
    }

    private function waitBeforeRetry(int $attempt): void
    {
        Sleep::sleep(min(2 ** $attempt, 8));
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
