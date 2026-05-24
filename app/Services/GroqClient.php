<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class GroqClient
{
    public function __construct(
        private string $model,
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
        $cacheKey = 'ai:' . $generation . ':' . md5($systemPrompt . '|' . $userPrompt . '|' . $this->model);

        return Cache::remember($cacheKey, 86400 * 30, function () use ($systemPrompt, $userPrompt, $jsonMode): string {
            $body = [
                'model' => $this->model,
                'temperature' => $this->temperature,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ];

            if ($jsonMode) {
                $body['response_format'] = ['type' => 'json_object'];
            }

            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post($this->apiChat, $body)
                ->throw();

            /** @var string|null $content */
            $content = $response->json('choices.0.message.content');

            throw_if($content === null, RuntimeException::class, 'Empty response from AI');

            return $content;
        });
    }
}
