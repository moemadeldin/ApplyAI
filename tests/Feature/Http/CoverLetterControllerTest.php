<?php

declare(strict_types=1);

use App\Models\CustomJobVacancy;
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
                'message' => ['content' => 'Generated cover letter text'],
            ]],
        ], Response::HTTP_OK),
    ]);
});

test('generates cover letter for authenticated user with resume', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume text']))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->getJson(route('cover-letter.generate', ['customJobVacancy' => $vacancy->id]));

    $response->assertStatus(Response::HTTP_CREATED)
        ->assertJsonStructure(['data' => ['cover_letter']]);
});

test('returns 422 when resume has no extracted text', function (): void {
    $user = User::factory()->has(Resume::factory(['extracted_text' => null]))->create();
    $vacancy = CustomJobVacancy::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->getJson(route('cover-letter.generate', ['customJobVacancy' => $vacancy->id]));

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
});

test('returns 401 when not authenticated', function (): void {
    $vacancy = CustomJobVacancy::factory()->create();

    $response = $this->getJson(route('cover-letter.generate', ['customJobVacancy' => $vacancy->id]));

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});
