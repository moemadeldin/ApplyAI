<?php

declare(strict_types=1);

use App\Actions\CustomJobVacancy\CreateCustomJobVacancyAction;
use App\Enums\ProcessingStatus;
use App\Jobs\ProcessCustomJobVacancyJob;
use App\Models\CustomJobVacancy;
use App\Models\User;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

function vacancyControllerParseJson(): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode([
                'title' => 'Laravel Developer',
                'company' => 'Tech Corp',
                'skills_required' => 'Laravel, PHP',
                'responsibilities' => 'Build APIs',
                'requirements' => '3+ years experience',
                'experience_years_min' => 3,
                'experience_years_max' => 5,
                'nice_to_have' => 'React knowledge',
                'location' => 'Remote',
            ], JSON_THROW_ON_ERROR)]]],
        ]],
    ]);
}

function vacancyControllerEvaluateJson(): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode([
                'score' => 85,
                'feedback' => ['strengths' => ['Laravel'], 'weaknesses' => []],
                'suggestions' => 'Keep it up',
            ], JSON_THROW_ON_ERROR)]]],
        ]],
    ]);
}

function vacancyControllerText(string $text): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
        ]],
    ]);
}

function vacancyControllerQaJson(): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode([
                'qa' => [
                    ['question' => 'What is Laravel?', 'answer' => 'A PHP framework.'],
                    ['question' => 'Tell us about your experience.', 'answer' => '5 years.'],
                ],
            ], JSON_THROW_ON_ERROR)]]],
        ]],
    ]);
}

function vacancyControllerEmptyParseJson(): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => '[]']]],
        ]],
    ]);
}

function vacancyControllerCreateUser(): User
{
    $user = User::factory()->create();
    $user->resume()->create([
        'name' => 'resume.pdf',
        'path' => 'resumes/test.pdf',
        'extracted_text' => '5 years Laravel experience',
    ]);

    return $user;
}

function vacancyControllerFullGeminiFake(): void
{
    Gemini::fake([
        vacancyControllerParseJson(),
        vacancyControllerEvaluateJson(),
        vacancyControllerText('Optimized resume content'),
        vacancyControllerQaJson(),
        vacancyControllerText('Generated cover letter text'),
    ]);
}

beforeEach(function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            '# Laravel Developer at Tech Corp'.PHP_EOL.'We need a Laravel developer with 3+ years experience.',
            Response::HTTP_OK
        ),
    ]);
});

describe('CustomJobVacancyController', function (): void {
    it('can list custom job vacancies', function (): void {
        $user = User::factory()->create();
        CustomJobVacancy::factory()->count(3)->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('custom-vacancies.index'));

        $response->assertOk();
    });

    it('queues processing and returns 202 with polling info', function (): void {
        Queue::fake();

        $user = vacancyControllerCreateUser();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('custom-vacancies.store'), [
            'job_text' => 'Looking for a Laravel developer with 5 years experience.',
        ]);

        $response->assertStatus(Response::HTTP_ACCEPTED);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => ['vacancy_id', 'application_id', 'status', 'status_url', 'estimated_time_seconds'],
        ]);
        $response->assertJsonPath('data.status', ProcessingStatus::PENDING->value);

        $vacancy = CustomJobVacancy::query()->firstOrFail();

        expect($vacancy->status)->toBe(ProcessingStatus::PENDING)
            ->and($vacancy->job_text)->toBe('Looking for a Laravel developer with 5 years experience.');

        $response->assertJsonPath('data.status_url', route('custom-vacancies.status', $vacancy));

        Queue::assertPushed(ProcessCustomJobVacancyJob::class);
    });

    it('can store a custom job vacancy from a URL and process it to completion', function (): void {
        $user = vacancyControllerCreateUser();

        Sanctum::actingAs($user);

        vacancyControllerFullGeminiFake();

        $response = $this->postJson(route('custom-vacancies.store'), [
            'job_url' => 'https://example.com/jobs/123',
        ]);

        $response->assertStatus(Response::HTTP_ACCEPTED);

        $vacancy = CustomJobVacancy::query()->firstOrFail();
        $application = $vacancy->customJobApplications()->firstOrFail();

        expect($vacancy->status)->toBe(ProcessingStatus::COMPLETED)
            ->and($vacancy->current_step)->toBe('completed')
            ->and($vacancy->job_url)->toBe('https://example.com/jobs/123')
            ->and($vacancy->job_text)->toContain('Laravel Developer at Tech Corp')
            ->and($vacancy->title)->toBe('Laravel Developer')
            ->and($application->status)->toBe(ProcessingStatus::COMPLETED)
            ->and($application->compatibility_score)->toBe(85)
            ->and($application->cover_letter)->toBe('Generated cover letter text')
            ->and($application->mockInterview)->not->toBeNull();
    });

    it('can store a custom job vacancy from a URL with special characters', function (): void {
        Queue::fake();

        $user = vacancyControllerCreateUser();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('custom-vacancies.store'), [
            'job_url' => 'https://wuzzuf.net/jobs/p/abc-full-stack-developer?o=1&l=sp&t=sj&a=laravel|search-v3|hpb',
        ]);

        $response->assertStatus(Response::HTTP_ACCEPTED);

        Queue::assertPushed(ProcessCustomJobVacancyJob::class);
    });

    it('rejects storing a vacancy without either job text or a URL', function (): void {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('custom-vacancies.store'), [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['job_text', 'job_url']);
    });

    it('can preview a job vacancy from a URL', function (): void {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        Gemini::fake([vacancyControllerParseJson()]);

        $response = $this->postJson(route('custom-vacancies.preview'), [
            'job_url' => 'https://example.com/jobs/123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.vacancy.title', 'Laravel Developer')
            ->assertJsonPath('data.vacancy.company', 'Tech Corp');
    });

    it('accepts real-world URLs with special characters in the query string', function (): void {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        Gemini::fake([vacancyControllerParseJson()]);

        $response = $this->postJson(route('custom-vacancies.preview'), [
            'job_url' => 'https://wuzzuf.net/jobs/p/abc-full-stack-developer?o=1&l=sp&t=sj&a=laravel|search-v3|hpb',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.vacancy.title', 'Laravel Developer');
    });

    it('rejects previewing an invalid URL', function (): void {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('custom-vacancies.preview'), [
            'job_url' => 'not-a-url',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    });

    it('returns 422 when previewing a URL whose page blocks automated access', function (): void {
        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstance();

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

    it('returns 422 when previewing a page with no parseable job details', function (): void {
        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstance();

        Http::fake([
            'https://r.jina.ai/*' => Http::response(
                '# Not a job page'.PHP_EOL.'This page has some content but no job details.',
                Response::HTTP_OK
            ),
        ]);

        Gemini::fake([vacancyControllerEmptyParseJson()]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('custom-vacancies.preview'), [
            'job_url' => 'https://example.com/jobs/123',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => "Couldn't extract job details from this page."]);
    });

    it('returns 422 when storing a URL whose page blocks automated access', function (): void {
        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstance();

        Http::fake([
            'https://r.jina.ai/*' => Http::response(
                "Title: Just a moment...\nURL Source: https://wuzzuf.net/jobs/p/123\nMarkdown Content:\nJust a moment...\n\nPerforming security verification...",
                Response::HTTP_OK
            ),
        ]);

        $user = vacancyControllerCreateUser();
        Sanctum::actingAs($user);

        $this->postJson(route('custom-vacancies.store'), [
            'job_url' => 'https://wuzzuf.net/jobs/p/123',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => 'The website is blocking automated access. Try pasting the job text instead.']);
    });

    it('marks vacancy as failed when stored URL yields no parseable job details', function (): void {
        Queue::fake();

        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstance();

        Http::fake([
            'https://r.jina.ai/*' => Http::response(
                '# Not a job page'.PHP_EOL.'This page has some content but no job details.',
                Response::HTTP_OK
            ),
        ]);

        Gemini::fake([vacancyControllerEmptyParseJson()]);

        $user = vacancyControllerCreateUser();
        Sanctum::actingAs($user);

        $this->postJson(route('custom-vacancies.store'), [
            'job_url' => 'https://example.com/jobs/123',
        ])->assertStatus(Response::HTTP_ACCEPTED);

        $vacancy = CustomJobVacancy::query()->firstOrFail();
        $application = $vacancy->customJobApplications()->firstOrFail();

        $job = new ProcessCustomJobVacancyJob($vacancy, $application);

        try {
            $job->handle(resolve(CreateCustomJobVacancyAction::class));
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (RuntimeException) {
            // expected
        }

        $vacancy->refresh();
        $application->refresh();

        expect($vacancy->status)->toBe(ProcessingStatus::FAILED)
            ->and($vacancy->error_message)->toContain("Couldn't extract job details from this page.")
            ->and($application->status)->toBe(ProcessingStatus::FAILED);
    });

    it('requires authentication to preview a job URL', function (): void {
        $response = $this->postJson(route('custom-vacancies.preview'), [
            'job_url' => 'https://example.com/jobs/123',
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    });

    it('can show a custom job vacancy', function (): void {
        $user = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('custom-vacancies.show', $vacancy));

        $response->assertOk();
    });

    it('can delete a custom job vacancy', function (): void {
        $user = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('custom-vacancies.destroy', $vacancy));

        $response->assertNoContent();
    });

    it('requires authentication to list vacancies', function (): void {
        $response = $this->getJson(route('custom-vacancies.index'));

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    });

    it('requires authentication to create vacancy', function (): void {
        $response = $this->postJson(route('custom-vacancies.store'), [
            'job_text' => 'Test job',
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    });
});

describe('CustomJobVacancyStatus', function (): void {
    it('returns pending status for a queued vacancy', function (): void {
        $user = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->getJson(route('custom-vacancies.status', $vacancy))
            ->assertOk()
            ->assertJsonPath('data.status', ProcessingStatus::PENDING->value)
            ->assertJsonMissingPath('data.vacancy');
    });

    it('returns processing status with the current step', function (): void {
        $user = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($user)->create([
            'status' => ProcessingStatus::PROCESSING->value,
            'current_step' => 'evaluate',
        ]);

        Sanctum::actingAs($user);

        $this->getJson(route('custom-vacancies.status', $vacancy))
            ->assertOk()
            ->assertJsonPath('data.status', ProcessingStatus::PROCESSING->value)
            ->assertJsonPath('data.current_step', 'evaluate');
    });

    it('returns completed status with full results', function (): void {
        $user = vacancyControllerCreateUser();

        Sanctum::actingAs($user);

        vacancyControllerFullGeminiFake();

        $this->postJson(route('custom-vacancies.store'), [
            'job_text' => 'Looking for a Laravel developer with 5 years experience.',
        ])->assertStatus(Response::HTTP_ACCEPTED);

        $vacancy = CustomJobVacancy::query()->firstOrFail();

        $this->getJson(route('custom-vacancies.status', $vacancy))
            ->assertOk()
            ->assertJsonPath('data.status', ProcessingStatus::COMPLETED->value)
            ->assertJsonPath('data.current_step', 'completed')
            ->assertJsonPath('data.vacancy.title', 'Laravel Developer')
            ->assertJsonPath('data.application.compatibility_score', 85)
            ->assertJsonPath('data.application.mock_interview.questions.0.question', 'What is Laravel?');
    });

    it('returns failed status with an error message', function (): void {
        $user = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($user)->create([
            'status' => ProcessingStatus::FAILED->value,
            'current_step' => 'failed',
            'error_message' => 'AI service unavailable',
        ]);

        Sanctum::actingAs($user);

        $this->getJson(route('custom-vacancies.status', $vacancy))
            ->assertOk()
            ->assertJsonPath('data.status', ProcessingStatus::FAILED->value)
            ->assertJsonPath('data.error', 'AI service unavailable')
            ->assertJsonMissingPath('data.vacancy');
    });

    it('returns 404 when another user requests the status', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($owner)->create();

        Sanctum::actingAs($other);

        $this->getJson(route('custom-vacancies.status', $vacancy))
            ->assertNotFound();
    });

    it('requires authentication to view status', function (): void {
        $user = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($user)->create();

        $this->getJson(route('custom-vacancies.status', $vacancy))
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    });
});
