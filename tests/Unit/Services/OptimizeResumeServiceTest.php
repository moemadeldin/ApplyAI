<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\OptimizeResumeService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;

test('optimize returns result', function (): void {
    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => 'Optimized resume content',
                ],
            ]],
        ], Response::HTTP_OK),
    ]);

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
