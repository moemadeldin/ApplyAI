<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EvaluateResumeWithAIService;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;

function evaluateResumeGeminiJson(array $data): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($data, JSON_THROW_ON_ERROR)]]],
        ]],
    ]);
}

test('evaluate returns result', function (): void {
    Gemini::fake([evaluateResumeGeminiJson([
        'score' => 85,
        'feedback' => ['strengths' => ['PHP'], 'weaknesses' => []],
        'suggestions' => 'Good',
    ])]);

    $service = resolve(EvaluateResumeWithAIService::class);
    $result = $service->evaluate('My resume', 'Job desc');

    expect($result)->toHaveKey('score');
});
