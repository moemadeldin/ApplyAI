<?php

declare(strict_types=1);

use App\Models\User;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

function previewUrlGeminiJson(array $data): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($data, JSON_THROW_ON_ERROR)]]],
        ]],
    ]);
}

test('previews a job URL and returns the parsed vacancy', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            '# Laravel Developer at Tech Corp'.PHP_EOL.'We need a Laravel developer with 3+ years experience.',
            Response::HTTP_OK
        ),
    ]);

    Gemini::fake([previewUrlGeminiJson([
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
    ])]);

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson(route('custom-vacancies.preview'), [
        'job_url' => 'https://example.com/jobs/123',
    ])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.vacancy.title', 'Laravel Developer')
        ->assertJsonPath('data.vacancy.company', 'Tech Corp')
        ->assertJsonPath('message', 'Job vacancy parsed successfully.');
});

test('returns 422 for an invalid job URL', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson(route('custom-vacancies.preview'), [
        'job_url' => 'not-a-url',
    ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['job_url']);
});

test('returns 422 when the page blocks automated access', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            "Title: Just a moment...\nURL Source: https://wuzzuf.net/jobs/p/123\nMarkdown Content:\nJust a moment...\n\nPerforming security verification...",
            Response::HTTP_OK
        ),
    ]);

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson(route('custom-vacancies.preview'), [
        'job_url' => 'https://wuzzuf.net/jobs/p/123',
    ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJson(['message' => 'The website is blocking automated access. Try pasting the job text instead.']);
});

test('returns 422 when the AI cannot parse job details', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            '# Not a job page'.PHP_EOL.'This page has some content but no job details.',
            Response::HTTP_OK
        ),
    ]);

    Gemini::fake([previewUrlGeminiJson([])]);

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson(route('custom-vacancies.preview'), [
        'job_url' => 'https://example.com/jobs/123',
    ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJson(['message' => "Couldn't extract job details from this page."]);
});
