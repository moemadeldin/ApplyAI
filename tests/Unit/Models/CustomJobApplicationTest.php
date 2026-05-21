<?php

declare(strict_types=1);

use App\Enums\MockInterviewStatus;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\MockInterview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('factory creates a custom job application', function (): void {
    expect(CustomJobApplication::factory()->create())->not->toBeNull();
});

test('belongs to a user', function (): void {
    $user = User::factory()->create();
    $application = CustomJobApplication::factory()->for($user)->create();

    expect($application->user)->toBeInstanceOf(User::class)
        ->and($application->user->id)->toBe($user->id);
});

test('belongs to a custom job vacancy', function (): void {
    $vacancy = CustomJobVacancy::factory()->create();
    $application = CustomJobApplication::factory()->create([
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    expect($application->customJobVacancy)->toBeInstanceOf(CustomJobVacancy::class)
        ->and($application->customJobVacancy->id)->toBe($vacancy->id);
});

test('has one mock interview', function (): void {
    $application = CustomJobApplication::factory()->create();
    MockInterview::factory()->create([
        'application_id' => $application->id,
    ]);

    expect($application->mockInterview)->toBeInstanceOf(MockInterview::class);
});

test('filterStatus scope filters by mock interview status', function (): void {
    $qualifiedApp = CustomJobApplication::factory()->create();
    MockInterview::factory()->create([
        'application_id' => $qualifiedApp->id,
        'status' => MockInterviewStatus::QUALIFIED->value,
    ]);

    $disqualifiedApp = CustomJobApplication::factory()->create();
    MockInterview::factory()->create([
        'application_id' => $disqualifiedApp->id,
        'status' => MockInterviewStatus::DISQUALIFIED->value,
    ]);

    $results = CustomJobApplication::query()->filterStatus(MockInterviewStatus::QUALIFIED->value)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($qualifiedApp->id);
});

test('filterStatus scope does not filter when status is null', function (): void {
    CustomJobApplication::factory()->count(2)->create();

    $results = CustomJobApplication::query()->filterStatus(null)->get();

    expect($results)->toHaveCount(2);
});

test('casts feedback to array', function (): void {
    $feedback = ['strengths' => ['PHP'], 'weaknesses' => []];
    $application = CustomJobApplication::factory()->create([
        'feedback' => $feedback,
    ]);

    expect($application->feedback)->toBeArray()
        ->and($application->feedback)->toMatchArray($feedback);
});

test('casts compatibility score to integer', function (): void {
    $application = CustomJobApplication::factory()->create([
        'compatibility_score' => 85,
    ]);

    expect($application->compatibility_score)->toBeInt()
        ->and($application->compatibility_score)->toBe(85);
});
