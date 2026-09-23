<?php

declare(strict_types=1);

use App\Models\CustomJobVacancy;
use App\Models\Resume;
use App\Models\User;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

function coverLetterControllerGeminiText(string $text): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
        ]],
    ]);
}

beforeEach(function (): void {
    Gemini::fake([coverLetterControllerGeminiText('Generated cover letter text')]);
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
