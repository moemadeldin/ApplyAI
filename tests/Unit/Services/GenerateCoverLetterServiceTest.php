<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GenerateCoverLetterService;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use ReflectionMethod;

function coverLetterGeminiText(string $text): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
        ]],
    ]);
}

describe('GenerateCoverLetterService', function (): void {
    it('generates cover letter and sanitizes text', function (): void {
        Gemini::fake([coverLetterGeminiText('  Hello World.  ')]);

        $service = resolve(GenerateCoverLetterService::class);
        $result = $service->generate('My resume', 'Job desc');

        expect($result)->toBeString();
    });

    it('handles newlines in generated text', function (): void {
        Gemini::fake([coverLetterGeminiText("Line1\n\nLine2\r\nLine3")]);

        $service = resolve(GenerateCoverLetterService::class);
        $result = $service->generate('My resume', 'Job desc');

        expect($result)->not->toContain("\n");
    });

    it('handles literal backslash n sequences', function (): void {
        Gemini::fake([coverLetterGeminiText('Hello\\n\\nWorld')]);

        $service = resolve(GenerateCoverLetterService::class);
        $result = $service->generate('My resume', 'Job desc');

        expect($result)->not->toContain('\\n');
    });

    it('collapses multiple whitespace', function (): void {
        Gemini::fake([coverLetterGeminiText('Hello    World   Test')]);

        $service = resolve(GenerateCoverLetterService::class);
        $result = $service->generate('My resume', 'Job desc');

        expect($result)->not->toContain('  ');
    });

    it('trims whitespace from result', function (): void {
        Gemini::fake([coverLetterGeminiText('  Trimmed text  ')]);

        $service = resolve(GenerateCoverLetterService::class);
        $result = $service->generate('My resume', 'Job desc');

        expect($result)->toBe('Trimmed text');
    });

    it('returns empty string for null input in sanitizeText', function (): void {
        $service = resolve(GenerateCoverLetterService::class);
        $method = new ReflectionMethod($service, 'sanitizeText');
        $result = $method->invoke($service, null);

        expect($result)->toBe('');
    });
});
