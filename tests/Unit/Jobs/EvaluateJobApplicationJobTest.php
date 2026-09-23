<?php

declare(strict_types=1);

use App\Jobs\EvaluateJobApplicationJob;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\Resume;
use App\Models\User;
use App\Services\EvaluateResumeWithAIService;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function evaluateJobGeminiJson(array $data): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($data, JSON_THROW_ON_ERROR)]]],
        ]],
    ]);
}

beforeEach(function (): void {
    Gemini::fake([evaluateJobGeminiJson([
        'score' => 85,
        'feedback' => ['strengths' => ['PHP'], 'weaknesses' => []],
        'suggestions' => 'Good',
    ])]);
});

test('evaluates application and updates with score', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume']))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);
    $application = CustomJobApplication::factory()->create([
        'user_id' => $user->id,
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    $job = new EvaluateJobApplicationJob($application);
    $job->handle(resolve(EvaluateResumeWithAIService::class));

    $application->refresh();
    expect($application->compatibility_score)->toBe(85)
        ->and($application->applied_at)->not->toBeNull()
        ->and($application->reviewed_at)->not->toBeNull();
});

test('throws when resume has no extracted text', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => null]))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);
    $application = CustomJobApplication::factory()->create([
        'user_id' => $user->id,
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    $job = new EvaluateJobApplicationJob($application);
    $job->handle(resolve(EvaluateResumeWithAIService::class));
})->throws(RuntimeException::class);

test('has retry configuration', function (): void {
    $application = CustomJobApplication::factory()->create();
    $job = new EvaluateJobApplicationJob($application);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe(30);
});
