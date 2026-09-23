<?php

declare(strict_types=1);

use App\Actions\CustomJobVacancy\CreateCustomJobVacancyAction;
use App\Enums\ProcessingStatus;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\Resume;
use App\Models\User;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function actionJsonResponse(array $data): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($data)]]],
        ]],
    ]);
}

function actionTextResponse(string $text): GenerateContentResponse
{
    return GenerateContentResponse::fake([
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
        ]],
    ]);
}

function actionVacancyPayload(): array
{
    return [
        'title' => 'PHP Developer',
        'company' => 'Tech Corp',
        'description' => 'We need a dev',
        'location' => 'Remote',
        'employment_type' => 'Full-time',
        'responsibilities' => 'Write code',
        'requirements' => 'PHP',
        'skills_required' => 'Laravel',
        'experience_years_min' => 2,
        'experience_years_max' => 5,
        'expected_salary' => '80000',
        'category' => 'Tech',
    ];
}

function actionEvaluationPayload(int $score): array
{
    return [
        'score' => $score,
        'feedback' => ['strengths' => ['PHP'], 'weaknesses' => []],
        'suggestions' => 'Good resume',
    ];
}

function actionQaPayload(): array
{
    return [
        'qa' => [
            ['question' => 'Q1?', 'answer' => 'A1'],
            ['question' => 'Q2?', 'answer' => 'A2'],
        ],
    ];
}

function actionPendingVacancy(User $user): CustomJobVacancy
{
    $vacancy = CustomJobVacancy::factory()->for($user)->create([
        'status' => ProcessingStatus::PENDING->value,
    ]);

    CustomJobApplication::factory()->for($user)->create([
        'custom_job_vacancy_id' => $vacancy->id,
        'status' => ProcessingStatus::PENDING->value,
    ]);

    return $vacancy->refresh();
}

beforeEach(function (): void {
    Cache::flush();
    Sleep::fake();

    $this->user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume text for testing']))->create();
});

test('creates vacancy with high score - generates all content', function (): void {
    Gemini::fake([
        actionJsonResponse(actionVacancyPayload()),
        actionJsonResponse(actionEvaluationPayload(85)),
        actionTextResponse('Optimized resume content'),
        actionJsonResponse(actionQaPayload()),
        actionTextResponse('Cover letter content'),
    ]);

    $vacancy = actionPendingVacancy($this->user);

    $action = resolve(CreateCustomJobVacancyAction::class);
    $result = $action->handle($vacancy, $this->user);

    expect($result)
        ->toHaveKey('vacancy')
        ->toHaveKey('application')
        ->toHaveKey('mock_interview')
        ->and($result['vacancy']->title)->toBe('PHP Developer')
        ->and($result['application']->compatibility_score)->toBe(85)
        ->and($result['application']->optimized_resume)->toBe('Optimized resume content')
        ->and($result['application']->cover_letter)->toBe('Cover letter content')
        ->and($result['mock_interview'])->not->toBeNull();
});

test('creates vacancy with low score - no optimized resume or mock interview', function (): void {
    Gemini::fake([
        actionJsonResponse(actionVacancyPayload()),
        actionJsonResponse(actionEvaluationPayload(30)),
        actionTextResponse('Optimized resume content'),
        actionJsonResponse(actionQaPayload()),
    ]);

    $vacancy = actionPendingVacancy($this->user);

    $action = resolve(CreateCustomJobVacancyAction::class);
    $result = $action->handle($vacancy, $this->user);

    expect($result)
        ->toHaveKey('vacancy')
        ->toHaveKey('application')
        ->toHaveKey('mock_interview')
        ->and($result['application']->compatibility_score)->toBe(30)
        ->and($result['application']->optimized_resume)->toBeNull()
        ->and($result['application']->cover_letter)->toBeNull()
        ->and($result['mock_interview'])->toBeNull();
});

test('aborts when user has no resume', function (): void {
    $userWithoutResume = User::factory()->create();
    $vacancy = actionPendingVacancy($userWithoutResume);

    $action = resolve(CreateCustomJobVacancyAction::class);
    $action->handle($vacancy, $userWithoutResume);
})->throws(HttpException::class);
