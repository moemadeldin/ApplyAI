<?php

declare(strict_types=1);

use App\Actions\CustomJobVacancy\GenerateCustomMockInterviewAction;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\MockInterview;
use App\Models\Resume;
use App\Models\User;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function generateMockGeminiJson(array $data): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($data, JSON_THROW_ON_ERROR)]]],
        ]],
    ]);
}

beforeEach(function (): void {
    Gemini::fake([generateMockGeminiJson([
        'qa' => [
            ['question' => 'Q1?', 'answer' => 'A1'],
            ['question' => 'Q2?', 'answer' => 'A2'],
        ],
    ])]);
});

test('generates mock interview successfully', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume']))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);
    $application = CustomJobApplication::factory()->create([
        'user_id' => $user->id,
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    $action = resolve(GenerateCustomMockInterviewAction::class);
    $mockInterview = $action->handle($application);

    expect($mockInterview)
        ->toBeInstanceOf(MockInterview::class)
        ->and($mockInterview->application_id)->toBe($application->id)
        ->and($mockInterview->questions)->toHaveCount(2);
});

test('throws when resume has no extracted text', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => null]))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);
    $application = CustomJobApplication::factory()->create([
        'user_id' => $user->id,
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    $action = resolve(GenerateCustomMockInterviewAction::class);
    $action->handle($application);
})->throws(Exception::class, 'Resume not found or has no extracted text.');
