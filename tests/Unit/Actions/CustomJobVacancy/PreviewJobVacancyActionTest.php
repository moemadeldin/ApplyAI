<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\CustomJobVacancy;

use App\Actions\CustomJobVacancy\PreviewJobVacancyAction;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

test('previews a job from a URL without persisting anything', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            '# Laravel Developer at Tech Corp'.PHP_EOL.'We need a Laravel developer with 3+ years experience.',
            Response::HTTP_OK
        ),
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'title' => 'Laravel Developer',
                        'company' => 'Tech Corp',
                        'description' => 'We need a developer',
                        'location' => 'Remote',
                        'employment_type' => 'Full-time',
                        'responsibilities' => 'Build APIs',
                        'requirements' => '3+ years experience',
                        'skills_required' => 'Laravel, PHP',
                        'experience_years_min' => 3,
                        'experience_years_max' => 5,
                        'expected_salary' => '80000',
                        'category' => 'Tech',
                    ]),
                ],
            ]],
        ], Response::HTTP_OK),
    ]);

    $action = resolve(PreviewJobVacancyAction::class);
    $result = $action->handle('https://example.com/jobs/123');

    expect($result['title'])->toBe('Laravel Developer')
        ->and($result['company'])->toBe('Tech Corp')
        ->and($result['location'])->toBe('Remote');
});

test('preview action throws when the page cannot be fetched', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response('', Response::HTTP_INTERNAL_SERVER_ERROR),
        'https://example.com/*' => Http::response('', Response::HTTP_INTERNAL_SERVER_ERROR),
    ]);

    $action = resolve(PreviewJobVacancyAction::class);
    $action->handle('https://example.com/jobs/123');
})->throws(RuntimeException::class, 'Unable to fetch the job page.');

test('preview action throws when the page blocks automated access', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            "Title: Just a moment...\nURL Source: https://wuzzuf.net/jobs/p/123\nMarkdown Content:\nJust a moment...\n\nPerforming security verification...",
            Response::HTTP_OK
        ),
    ]);

    $action = resolve(PreviewJobVacancyAction::class);
    $action->handle('https://wuzzuf.net/jobs/p/123');
})->throws(RuntimeException::class, 'The website is blocking automated access. Try pasting the job text instead.');

test('preview action throws when the page contains no parseable job details', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            '# Not a job page'.PHP_EOL.'This page has some content but no job details.',
            Response::HTTP_OK
        ),
        '*' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([])],
            ]],
        ], Response::HTTP_OK),
    ]);

    $action = resolve(PreviewJobVacancyAction::class);
    $action->handle('https://example.com/jobs/123');
})->throws(RuntimeException::class, "Couldn't extract job details from this page.");
