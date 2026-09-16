<?php

declare(strict_types=1);

namespace App\Services;

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
    private const int MAX_ATTEMPTS_PER_MODEL = 3;

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

    private function send(string $systemPrompt, string $userPrompt, bool $jsonMode): string
    {
        $generation = $this->cacheGet('ai:generation', 0);
        $generation = is_int($generation) ? $generation : 0;

        $requestId = mb_substr(md5(uniqid((string) mt_rand(), true)), 0, 8);

        $lastException = null;

        foreach ($this->models as $model) {
            $cacheKey = 'ai:gemini:'.$generation.':'.md5($systemPrompt.'|'.$userPrompt.'|'.$model);

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

                $this->cachePut($cacheKey, $content);

                return $content;
            } catch (Throwable $e) {
                $lastException = $e;

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
                    'ai:gemini:'.$generation.':'.md5($systemPrompt.'|'.$userPrompt.'|fallback'),
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
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS_PER_MODEL; $attempt++) {
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

                if ($e->getErrorCode() !== 429 && $attempt < self::MAX_ATTEMPTS_PER_MODEL && $this->isRetryable($e)) {
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

                if ($attempt < self::MAX_ATTEMPTS_PER_MODEL) {
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
            Cache::put($key, $content, 86400 * 30);
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
}
