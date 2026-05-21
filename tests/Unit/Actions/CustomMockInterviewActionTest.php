<?php

declare(strict_types=1);

use App\Actions\CustomMockInterviewAction;
use App\Enums\MockInterviewStatus;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\MockInterview;
use App\Models\Resume;
use App\Models\User;
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
                        'qa' => [
                            ['question' => 'Q1?', 'answer' => 'A1'],
                            ['question' => 'Q2?', 'answer' => 'A2'],
                        ],
                    ]),
                ],
            ]],
        ], Response::HTTP_OK),
    ]);
});

test('qualifies and stores questions successfully', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume']))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);
    $application = CustomJobApplication::factory()->create([
        'user_id' => $user->id,
        'custom_job_vacancy_id' => $vacancy->id,
    ]);
    MockInterview::factory()->create([
        'application_id' => $application->id,
        'status' => MockInterviewStatus::DISQUALIFIED->value,
    ]);

    $action = resolve(CustomMockInterviewAction::class);
    $questions = $action->handle($application);

    expect($questions)->toHaveCount(2)
        ->and($questions[0])->toMatchArray(['order' => 1, 'question' => 'Q1?', 'answer' => 'A1'])
        ->and($questions[1])->toMatchArray(['order' => 2, 'question' => 'Q2?', 'answer' => 'A2']);
});

test('storeQuestions filters out non-array qa entries', function (): void {
    $action = resolve(CustomMockInterviewAction::class);
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('storeQuestions');

    $user = User::factory()->has(Resume::factory())->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);
    $application = CustomJobApplication::factory()->create([
        'user_id' => $user->id,
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    $questions = $method->invoke($action, $application, [
        ['question' => 'Q1?', 'answer' => 'A1'],
        'not-an-array',
        ['question' => 'Q2?'],
    ]);

    expect($questions)->toHaveCount(1)
        ->and($questions[0])->toMatchArray(['order' => 1, 'question' => 'Q1?', 'answer' => 'A1']);
});

test('storeQuestions handles empty qa list', function (): void {
    $action = resolve(CustomMockInterviewAction::class);
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('storeQuestions');

    $user = User::factory()->has(Resume::factory())->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);
    $application = CustomJobApplication::factory()->create([
        'user_id' => $user->id,
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    $questions = $method->invoke($action, $application, [
        'not-array',
        123,
    ]);

    expect($questions)->toBeEmpty();
});

test('throws when mock interview not found', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume']))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);
    $application = CustomJobApplication::factory()->create([
        'user_id' => $user->id,
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    $action = resolve(CustomMockInterviewAction::class);
    $action->handle($application);
})->throws(Exception::class, 'Mock interview not found');
