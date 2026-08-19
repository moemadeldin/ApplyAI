<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ParseJobVacancyService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

test('parse returns parsed data', function (): void {
    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'title' => 'PHP Developer',
                        'company' => 'Tech Corp',
                        'description' => 'We need a developer',
                        'location' => 'Remote',
                        'employment_type' => 'Full-time',
                        'responsibilities' => 'Code',
                        'requirements' => 'PHP',
                        'skills_required' => 'Laravel',
                        'experience_years_min' => 2,
                        'experience_years_max' => 5,
                        'expected_salary' => '50000',
                        'category' => 'Tech',
                    ]),
                ],
            ]],
        ], Response::HTTP_OK),
    ]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job description text');

    expect($result)->toHaveKey('title');
});

test('stringOrNull returns null for empty string', function (): void {
    Http::fake(['*' => Http::response([
        'choices' => [[
            'message' => ['content' => json_encode([
                'title' => '', 'company' => 'Tech Corp', 'description' => 'desc',
                'location' => 'Remote', 'employment_type' => 'Full-time',
                'responsibilities' => 'Code', 'requirements' => 'PHP',
                'skills_required' => 'Laravel', 'experience_years_min' => 2,
                'experience_years_max' => 5, 'expected_salary' => '50000',
                'category' => 'Tech',
            ])],
        ]],
    ], Response::HTTP_OK)]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job description');

    expect($result['title'])->toBeNull();
});

test('normalizes part-time employment type', function (): void {
    Http::fake(['*' => Http::response([
        'choices' => [[
            'message' => ['content' => json_encode([
                'title' => 'Dev', 'company' => 'C', 'description' => 'd',
                'location' => 'R', 'employment_type' => 'Part-time',
                'responsibilities' => 'C', 'requirements' => 'P',
                'skills_required' => 'L', 'experience_years_min' => 2,
                'experience_years_max' => 5, 'expected_salary' => '30000',
                'category' => 'T',
            ])],
        ]],
    ], Response::HTTP_OK)]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job');

    expect($result['employment_type'])->toBe('part-time');
});

test('normalizes full-time employment type variants', function (): void {
    Http::fake(['*' => Http::response([
        'choices' => [[
            'message' => ['content' => json_encode([
                'title' => 'Dev', 'company' => 'C', 'description' => 'd',
                'location' => 'R', 'employment_type' => 'Full-time',
                'responsibilities' => 'C', 'requirements' => 'P',
                'skills_required' => 'L', 'experience_years_min' => 2,
                'experience_years_max' => 5, 'expected_salary' => '50000',
                'category' => 'T',
            ])],
        ]],
    ], Response::HTTP_OK)]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job');

    expect($result['employment_type'])->toBe('full-time');
});

test('normalizes salary by removing dollar signs and commas', function (): void {
    Http::fake(['*' => Http::response([
        'choices' => [[
            'message' => ['content' => json_encode([
                'title' => 'Dev', 'company' => 'C', 'description' => 'd',
                'location' => 'R', 'employment_type' => 'Full-time',
                'responsibilities' => 'C', 'requirements' => 'P',
                'skills_required' => 'L', 'experience_years_min' => 2,
                'experience_years_max' => 5, 'expected_salary' => '$75,000',
                'category' => 'T',
            ])],
        ]],
    ], Response::HTTP_OK)]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job');

    expect($result['expected_salary'])->toBe('75000');
});

test('returns null for non-numeric salary', function (): void {
    Http::fake(['*' => Http::response([
        'choices' => [[
            'message' => ['content' => json_encode([
                'title' => 'Dev', 'company' => 'C', 'description' => 'd',
                'location' => 'R', 'employment_type' => 'Full-time',
                'responsibilities' => 'C', 'requirements' => 'P',
                'skills_required' => 'L', 'experience_years_min' => 2,
                'experience_years_max' => 5, 'expected_salary' => 'negotiable',
                'category' => 'T',
            ])],
        ]],
    ], Response::HTTP_OK)]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job');

    expect($result['expected_salary'])->toBeNull();
});

test('throws when the AI returns no job details at all', function (): void {
    Http::fake(['*' => Http::response([
        'choices' => [[
            'message' => ['content' => json_encode([])],
        ]],
    ], Response::HTTP_OK)]);

    $service = resolve(ParseJobVacancyService::class);

    $service->parse('Job');
})->throws(RuntimeException::class, "Couldn't extract job details from this page.");
