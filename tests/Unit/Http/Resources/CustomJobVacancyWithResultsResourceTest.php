<?php

declare(strict_types=1);

use App\Http\Resources\CustomJobVacancyWithResultsResource;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\MockInterview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resource transforms with mock interview', function (): void {
    $vacancy = CustomJobVacancy::factory()->create();
    $application = CustomJobApplication::factory()->create([
        'custom_job_vacancy_id' => $vacancy->id,
    ]);
    $mockInterview = MockInterview::factory()->create([
        'application_id' => $application->id,
    ]);

    $resource = new CustomJobVacancyWithResultsResource([
        'vacancy' => $vacancy,
        'application' => $application,
        'mock_interview' => $mockInterview,
    ]);
    $data = $resource->toArray(request());

    expect($data)
        ->toHaveKey('vacancy')
        ->toHaveKey('application')
        ->toHaveKey('mock_interview')
        ->and($data['mock_interview'])->not->toBeNull();
});

test('resource transforms without mock interview', function (): void {
    $vacancy = CustomJobVacancy::factory()->create();
    $application = CustomJobApplication::factory()->create([
        'custom_job_vacancy_id' => $vacancy->id,
    ]);

    $resource = new CustomJobVacancyWithResultsResource([
        'vacancy' => $vacancy,
        'application' => $application,
        'mock_interview' => null,
    ]);
    $data = $resource->toArray(request());

    expect($data)
        ->toHaveKey('vacancy')
        ->toHaveKey('application')
        ->toHaveKey('mock_interview')
        ->and($data['mock_interview'])->toBeNull();
});

test('json structure constant', function (): void {
    expect(CustomJobVacancyWithResultsResource::JSON_STRUCTURE)->toBe([
        'vacancy',
        'application',
        'mock_interview',
    ]);
});

test('vacancy resource exposes the job url', function (): void {
    $vacancy = CustomJobVacancy::factory()->create([
        'job_url' => 'https://boards.greenhouse.io/acme/jobs/123',
    ]);

    $resource = new CustomJobVacancyWithResultsResource([
        'vacancy' => $vacancy,
        'application' => CustomJobApplication::factory()->create([
            'custom_job_vacancy_id' => $vacancy->id,
        ]),
        'mock_interview' => null,
    ]);
    $data = $resource->toArray(request());

    expect($data['vacancy']['job_url'])->toBe('https://boards.greenhouse.io/acme/jobs/123');
});
