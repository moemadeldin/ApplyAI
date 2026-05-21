<?php

declare(strict_types=1);

use App\Enums\EmploymentType;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('factory creates a custom job vacancy', function (): void {
    expect(CustomJobVacancy::factory()->create())->not->toBeNull();
});

test('belongs to a user', function (): void {
    $user = User::factory()->create();
    $vacancy = CustomJobVacancy::factory()->for($user)->create();

    expect($vacancy->user)->toBeInstanceOf(User::class)
        ->and($vacancy->user->id)->toBe($user->id);
});

test('has many custom job applications', function (): void {
    $vacancy = CustomJobVacancy::factory()->create();
    CustomJobApplication::factory()->count(3)->create([
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    expect($vacancy->customJobApplications)->toHaveCount(3)
        ->and($vacancy->customJobApplications->first())->toBeInstanceOf(CustomJobApplication::class);
});

test('casts employment type to enum', function (): void {
    $vacancy = CustomJobVacancy::factory()->create([
        'employment_type' => EmploymentType::FULL_TIME->value,
    ]);

    expect($vacancy->employment_type)->toBeInstanceOf(EmploymentType::class)
        ->and($vacancy->employment_type->value)->toBe('full-time');
});

test('uses uuid as primary key', function (): void {
    $vacancy = CustomJobVacancy::factory()->create();

    expect($vacancy->id)->toBeString();
});

test('supports soft deletes', function (): void {
    $vacancy = CustomJobVacancy::factory()->create();
    $vacancy->delete();

    expect(CustomJobVacancy::query()->find($vacancy->id))->toBeNull()
        ->and(CustomJobVacancy::withTrashed()->find($vacancy->id))->not->toBeNull();
});
