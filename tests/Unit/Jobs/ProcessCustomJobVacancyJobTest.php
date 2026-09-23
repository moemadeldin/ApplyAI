<?php

declare(strict_types=1);

use App\Enums\ProcessingStatus;
use App\Jobs\ProcessCustomJobVacancyJob;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\Resume;
use App\Models\User;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function processJobJsonResponse(array $data): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($data, JSON_THROW_ON_ERROR)]]],
        ]],
    ]);
}

function processJobTextResponse(string $text): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
        ]],
    ]);
}

function processJobVacancyPayload(): array
{
    return [
        'title' => 'Laravel Developer',
        'company' => 'Tech Corp',
        'description' => 'We need a dev',
        'location' => 'Remote',
        'employment_type' => 'Full-time',
        'responsibilities' => 'Build APIs',
        'requirements' => 'PHP + Laravel',
        'skills_required' => 'Laravel',
        'experience_years_min' => 2,
        'experience_years_max' => 5,
        'expected_salary' => '90000',
        'category' => 'Tech',
    ];
}

function processJobQaPayload(): array
{
    return [
        'qa' => [
            ['question' => 'Q1?', 'answer' => 'A1'],
            ['question' => 'Q2?', 'answer' => 'A2'],
        ],
    ];
}

/**
 * @return array{user: User, vacancy: CustomJobVacancy, application: CustomJobApplication}
 */
function processJobSetup(User $user): array
{
    $vacancy = CustomJobVacancy::factory()->for($user)->create([
        'status' => ProcessingStatus::PENDING->value,
        'job_text' => 'Looking for a Laravel developer with 5 years experience.',
    ]);

    $application = CustomJobApplication::factory()->for($user)->create([
        'custom_job_vacancy_id' => $vacancy->id,
        'status' => ProcessingStatus::PENDING->value,
    ]);

    return compact('user', 'vacancy', 'application');
}

beforeEach(function (): void {
    Cache::flush();
});

test('processes a pending vacancy to completion', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume text for testing']))->create();
    $setup = processJobSetup($user);

    Gemini::fake([
        processJobJsonResponse(processJobVacancyPayload()),
        processJobJsonResponse([
            'score' => 85,
            'feedback' => ['strengths' => ['Laravel'], 'weaknesses' => []],
            'suggestions' => 'Good',
        ]),
        processJobTextResponse('Optimized resume content'),
        processJobJsonResponse(processJobQaPayload()),
        processJobTextResponse('Cover letter content'),
    ]);

    $job = new ProcessCustomJobVacancyJob($setup['vacancy'], $setup['application']);
    $job->handle(resolve(App\Actions\CustomJobVacancy\CreateCustomJobVacancyAction::class));

    $vacancy = $setup['vacancy']->refresh();
    $application = $setup['application']->refresh();

    expect($vacancy->status)->toBe(ProcessingStatus::COMPLETED)
        ->and($vacancy->current_step)->toBe('completed')
        ->and($vacancy->title)->toBe('Laravel Developer')
        ->and($application->status)->toBe(ProcessingStatus::COMPLETED)
        ->and($application->compatibility_score)->toBe(85)
        ->and($application->optimized_resume)->toBe('Optimized resume content')
        ->and($application->cover_letter)->toBe('Cover letter content')
        ->and($application->mockInterview)->not->toBeNull()
        ->and($application->mockInterview->questions)->toHaveCount(2);
});

test('marks vacancy and application as failed when AI processing throws', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume text for testing']))->create();
    $setup = processJobSetup($user);

    Gemini::fake([
        processJobTextResponse('This is not valid JSON'),
    ]);

    $job = new ProcessCustomJobVacancyJob($setup['vacancy'], $setup['application']);

    try {
        $job->handle(resolve(App\Actions\CustomJobVacancy\CreateCustomJobVacancyAction::class));
        $this->fail('Expected a JsonException to be thrown.');
    } catch (JsonException) {
        // expected
    }

    $vacancy = $setup['vacancy']->refresh();
    $application = $setup['application']->refresh();

    expect($vacancy->status)->toBe(ProcessingStatus::FAILED)
        ->and($vacancy->current_step)->toBe('failed')
        ->and($vacancy->error_message)->not->toBeNull()
        ->and($application->status)->toBe(ProcessingStatus::FAILED);
});

test('has retry configuration', function (): void {
    $user = User::factory()->create();
    $setup = processJobSetup($user);

    $job = new ProcessCustomJobVacancyJob($setup['vacancy'], $setup['application']);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe(30);
});
