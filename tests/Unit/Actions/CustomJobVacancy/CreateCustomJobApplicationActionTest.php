<?php

declare(strict_types=1);

use App\Actions\CustomJobVacancy\CreateCustomJobApplicationAction;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'score' => 85,
                        'feedback' => ['strengths' => ['PHP'], 'weaknesses' => []],
                        'suggestions' => 'Keep going',
                    ]),
                ],
            ]],
        ], Response::HTTP_OK),
    ]);
});

test('creates application successfully', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume text']))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);

    $action = resolve(CreateCustomJobApplicationAction::class);
    $application = $action->handle('Cover letter content', $vacancy, $user);

    expect($application)
        ->toBeInstanceOf(CustomJobApplication::class)
        ->and($application->user_id)->toBe($user->id)
        ->and($application->custom_job_vacancy_id)->toBe($vacancy->id)
        ->and($application->compatibility_score)->toBe(85)
        ->and($application->cover_letter)->toBe('Cover letter content');
});

test('aborts when user has no resume', function (): void {
    $user = User::factory()->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);

    $action = resolve(CreateCustomJobApplicationAction::class);

    $action->handle('Cover letter', $vacancy, $user);
})->throws(HttpException::class);

test('aborts when resume has no extracted text', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => null]))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);

    $action = resolve(CreateCustomJobApplicationAction::class);

    $action->handle('Cover letter', $vacancy, $user);
})->throws(HttpException::class);
