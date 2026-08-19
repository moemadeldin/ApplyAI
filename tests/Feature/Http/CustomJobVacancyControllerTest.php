<?php

declare(strict_types=1);

use App\Http\Resources\CustomJobVacancyWithResultsResource;
use App\Models\CustomJobVacancy;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            '# Laravel Developer at Tech Corp'.PHP_EOL.'We need a Laravel developer with 3+ years experience.',
            Response::HTTP_OK
        ),
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'title' => 'Laravel Developer',
                        'company' => 'Tech Corp',
                        'skills_required' => 'Laravel, PHP',
                        'responsibilities' => 'Build APIs',
                        'requirements' => '3+ years experience',
                        'experience_years_min' => 3,
                        'experience_years_max' => 5,
                        'nice_to_have' => 'React knowledge',
                        'location' => 'Remote',
                    ]),
                ],
            ]],
        ], Response::HTTP_OK),
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

    it('can store a custom job vacancy and application', function (): void {
        $user = User::factory()->create();
        $user->resume()->create([
            'name' => 'resume.pdf',
            'path' => 'resumes/test.pdf',
            'extracted_text' => '5 years Laravel experience',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(route('custom-vacancies.store'), [
            'job_text' => 'Looking for a Laravel developer with 5 years experience.',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure(['data' => CustomJobVacancyWithResultsResource::JSON_STRUCTURE]);
    });

    it('can store a custom job vacancy from a URL', function (): void {
        $user = User::factory()->create();
        $user->resume()->create([
            'name' => 'resume.pdf',
            'path' => 'resumes/test.pdf',
            'extracted_text' => '5 years Laravel experience',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(route('custom-vacancies.store'), [
            'job_url' => 'https://example.com/jobs/123',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure(['data' => CustomJobVacancyWithResultsResource::JSON_STRUCTURE]);

        $vacancy = CustomJobVacancy::query()->firstOrFail();
        expect($vacancy->job_url)->toBe('https://example.com/jobs/123')
            ->and($vacancy->job_text)->toContain('Laravel Developer at Tech Corp');
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

        $response = $this->postJson(route('custom-vacancies.preview'), [
            'job_url' => 'https://wuzzuf.net/jobs/p/abc-full-stack-developer?o=1&l=sp&t=sj&a=laravel|search-v3|hpb',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.vacancy.title', 'Laravel Developer');
    });

    it('can store a custom job vacancy from a URL with special characters', function (): void {
        $user = User::factory()->create();
        $user->resume()->create([
            'name' => 'resume.pdf',
            'path' => 'resumes/test.pdf',
            'extracted_text' => '5 years Laravel experience',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(route('custom-vacancies.store'), [
            'job_url' => 'https://wuzzuf.net/jobs/p/abc-full-stack-developer?o=1&l=sp&t=sj&a=laravel|search-v3|hpb',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
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
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([])],
                ]],
            ], Response::HTTP_OK),
        ]);

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

        $user = User::factory()->create();
        $user->resume()->create([
            'name' => 'resume.pdf',
            'path' => 'resumes/test.pdf',
            'extracted_text' => '5 years Laravel experience',
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('custom-vacancies.store'), [
            'job_url' => 'https://wuzzuf.net/jobs/p/123',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => 'The website is blocking automated access. Try pasting the job text instead.']);
    });

    it('returns 422 when storing a URL that yields no parseable job details', function (): void {
        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstance();

        Http::fake([
            'https://r.jina.ai/*' => Http::response(
                '# Not a job page'.PHP_EOL.'This page has some content but no job details.',
                Response::HTTP_OK
            ),
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([])],
                ]],
            ], Response::HTTP_OK),
        ]);

        $user = User::factory()->create();
        $user->resume()->create([
            'name' => 'resume.pdf',
            'path' => 'resumes/test.pdf',
            'extracted_text' => '5 years Laravel experience',
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('custom-vacancies.store'), [
            'job_url' => 'https://example.com/jobs/123',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => "Couldn't extract job details from this page."]);
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
