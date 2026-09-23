<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\OptimizeResumeService;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use ReflectionMethod;

function optimizeResumeGeminiText(string $text): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
        ]],
    ]);
}

test('optimize returns result', function (): void {
    Gemini::fake([optimizeResumeGeminiText('Optimized resume content')]);

    $service = resolve(OptimizeResumeService::class);
    $result = $service->optimize('My resume', 'Job desc');

    expect($result)->toBeString();
});

test('sanitizeText returns empty string for null input', function (): void {
    $service = resolve(OptimizeResumeService::class);
    $method = new ReflectionMethod($service, 'sanitizeText');
    $result = $method->invoke($service, null);

    expect($result)->toBe('');
});

test('sanitizeText trims whitespace', function (): void {
    $service = resolve(OptimizeResumeService::class);
    $method = new ReflectionMethod($service, 'sanitizeText');
    $result = $method->invoke($service, '  Hello World  ');

    expect($result)->toBe('Hello World');
});
