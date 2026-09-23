<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Services\GeminiClient;
use App\Services\OpenAiCompatibleClient;
use App\Traits\APIResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class AiHealthController
{
    use APIResponses;

    public function __invoke(GeminiClient $gemini, ?OpenAiCompatibleClient $openRouter): JsonResponse
    {
        return $this->success([
            'gemini' => [
                'enabled' => $gemini->getModels() !== [],
                'models' => $gemini->getModels(),
            ],
            'openrouter' => $openRouter instanceof OpenAiCompatibleClient
                ? [
                    'enabled' => true,
                    'models' => $openRouter->getModels(),
                ]
                : ['enabled' => false, 'models' => []],
            'deepseek' => [
                'enabled' => (bool) config('deepseek.enabled'),
            ],
            'jina' => [
                'configured' => config('services.jina.api_key') !== '',
            ],
        ], 'AI services status.', Response::HTTP_OK);
    }
}
