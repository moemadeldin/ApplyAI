<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ParseJobVacancyService;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use RuntimeException;

function parseJobGeminiJson(array $data): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($data, JSON_THROW_ON_ERROR)]]],
        ]],
    ]);
}

test('parse returns parsed data', function (): void {
    Gemini::fake([parseJobGeminiJson([
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
    ])]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job description text');

    expect($result)->toHaveKey('title');
});

test('stringOrNull returns null for empty string', function (): void {
    Gemini::fake([parseJobGeminiJson([
        'title' => '', 'company' => 'Tech Corp', 'description' => 'desc',
        'location' => 'Remote', 'employment_type' => 'Full-time',
        'responsibilities' => 'Code', 'requirements' => 'PHP',
        'skills_required' => 'Laravel', 'experience_years_min' => 2,
        'experience_years_max' => 5, 'expected_salary' => '50000',
        'category' => 'Tech',
    ])]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job description');

    expect($result['title'])->toBeNull();
});

test('normalizes part-time employment type', function (): void {
    Gemini::fake([parseJobGeminiJson([
        'title' => 'Dev', 'company' => 'C', 'description' => 'd',
        'location' => 'R', 'employment_type' => 'Part-time',
        'responsibilities' => 'C', 'requirements' => 'P',
        'skills_required' => 'L', 'experience_years_min' => 2,
        'experience_years_max' => 5, 'expected_salary' => '30000',
        'category' => 'T',
    ])]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job');

    expect($result['employment_type'])->toBe('part-time');
});

test('normalizes full-time employment type variants', function (): void {
    Gemini::fake([parseJobGeminiJson([
        'title' => 'Dev', 'company' => 'C', 'description' => 'd',
        'location' => 'R', 'employment_type' => 'Full-time',
        'responsibilities' => 'C', 'requirements' => 'P',
        'skills_required' => 'L', 'experience_years_min' => 2,
        'experience_years_max' => 5, 'expected_salary' => '50000',
        'category' => 'T',
    ])]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job');

    expect($result['employment_type'])->toBe('full-time');
});

test('normalizes salary by removing dollar signs and commas', function (): void {
    Gemini::fake([parseJobGeminiJson([
        'title' => 'Dev', 'company' => 'C', 'description' => 'd',
        'location' => 'R', 'employment_type' => 'Full-time',
        'responsibilities' => 'C', 'requirements' => 'P',
        'skills_required' => 'L', 'experience_years_min' => 2,
        'experience_years_max' => 5, 'expected_salary' => '$75,000',
        'category' => 'T',
    ])]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job');

    expect($result['expected_salary'])->toBe('75000');
});

test('returns null for non-numeric salary', function (): void {
    Gemini::fake([parseJobGeminiJson([
        'title' => 'Dev', 'company' => 'C', 'description' => 'd',
        'location' => 'R', 'employment_type' => 'Full-time',
        'responsibilities' => 'C', 'requirements' => 'P',
        'skills_required' => 'L', 'experience_years_min' => 2,
        'experience_years_max' => 5, 'expected_salary' => 'negotiable',
        'category' => 'T',
    ])]);

    $service = resolve(ParseJobVacancyService::class);
    $result = $service->parse('Job');

    expect($result['expected_salary'])->toBeNull();
});

test('throws when the AI returns no job details at all', function (): void {
    Gemini::fake([parseJobGeminiJson([])]);

    $service = resolve(ParseJobVacancyService::class);

    $service->parse('Job');
})->throws(RuntimeException::class, "Couldn't extract job details from this page.");
