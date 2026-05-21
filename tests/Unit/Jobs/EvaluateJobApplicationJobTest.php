<?php

declare(strict_types=1);

use App\Jobs\EvaluateJobApplicationJob;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\Resume;
use App\Models\User;
use App\Services\EvaluateResumeWithAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'score' => 85,
                        'feedback' => ['strengths' => ['PHP'], 'weaknesses' => []],
                        'suggestions' => 'Good',
                    ]),
                ],
            ]],
        ], Response::HTTP_OK),
    ]);
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
