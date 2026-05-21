<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GenerateCoverLetterService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;

describe('GenerateCoverLetterService', function (): void {
    it('generates cover letter and sanitizes text', function (): void {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '  Hello World.  ',
                    ],
                ]],
            ], Response::HTTP_OK),
        ]);

        $service = resolve(GenerateCoverLetterService::class);
        $result = $service->generate('My resume', 'Job desc');

        expect($result)->toBeString();
    });

    it('handles newlines in generated text', function (): void {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => "Line1\n\nLine2\r\nLine3",
                    ],
                ]],
            ], Response::HTTP_OK),
        ]);

        $service = resolve(GenerateCoverLetterService::class);
        $result = $service->generate('My resume', 'Job desc');

        expect($result)->not->toContain("\n");
    });

    it('handles literal backslash n sequences', function (): void {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'Hello\\n\\nWorld',
                    ],
                ]],
            ], Response::HTTP_OK),
        ]);

        $service = resolve(GenerateCoverLetterService::class);
        $result = $service->generate('My resume', 'Job desc');

        expect($result)->not->toContain('\\n');
    });

    it('collapses multiple whitespace', function (): void {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'Hello    World   Test',
                    ],
                ]],
            ], Response::HTTP_OK),
        ]);

        $service = resolve(GenerateCoverLetterService::class);
        $result = $service->generate('My resume', 'Job desc');

        expect($result)->not->toContain('  ');
    });

    it('trims whitespace from result', function (): void {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '  Trimmed text  ',
                    ],
                ]],
            ], Response::HTTP_OK),
        ]);

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
