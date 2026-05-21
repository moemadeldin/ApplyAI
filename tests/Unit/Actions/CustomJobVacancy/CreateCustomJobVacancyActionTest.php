<?php

declare(strict_types=1);

use App\Actions\CustomJobVacancy\CreateCustomJobVacancyAction;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->has(Resume::factory(['extracted_text' => 'My resume text for testing']))->create();
});

test('creates vacancy with high score - generates all content', function (): void {
    Http::fakeSequence()
        ->push(['choices' => [['message' => [
            'content' => json_encode([
                'title' => 'PHP Developer', 'company' => 'Tech Corp', 'description' => 'We need a dev',
                'location' => 'Remote', 'employment_type' => 'Full-time',
                'responsibilities' => 'Write code', 'requirements' => 'PHP',
                'skills_required' => 'Laravel', 'experience_years_min' => 2,
                'experience_years_max' => 5, 'expected_salary' => '80000', 'category' => 'Tech',
            ]),
        ]]]])
        ->push(['choices' => [['message' => [
            'content' => json_encode([
                'score' => 85,
                'feedback' => ['strengths' => ['PHP'], 'weaknesses' => []],
                'suggestions' => 'Good resume',
            ]),
        ]]]])
        ->push(['choices' => [['message' => ['content' => 'Optimized resume content']]]])
        ->push(['choices' => [['message' => ['content' => 'Cover letter content']]]])
        ->push(['choices' => [['message' => [
            'content' => json_encode([
                'qa' => [
                    ['question' => 'Q1?', 'answer' => 'A1'],
                    ['question' => 'Q2?', 'answer' => 'A2'],
                ],
            ]),
        ]]]]);

    $action = resolve(CreateCustomJobVacancyAction::class);
    $result = $action->handle('Job description text', $this->user);

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
    Http::fakeSequence()
        ->push(['choices' => [['message' => [
            'content' => json_encode([
                'title' => 'PHP Developer', 'company' => 'Tech Corp', 'description' => 'We need a dev',
                'location' => 'Remote', 'employment_type' => 'Full-time',
                'responsibilities' => 'Write code', 'requirements' => 'PHP',
                'skills_required' => 'Laravel', 'experience_years_min' => 2,
                'experience_years_max' => 5, 'expected_salary' => '80000', 'category' => 'Tech',
            ]),
        ]]]])
        ->push(['choices' => [['message' => [
            'content' => json_encode([
                'score' => 30,
                'feedback' => ['strengths' => ['PHP'], 'weaknesses' => ['No experience']],
                'suggestions' => 'Improve resume',
            ]),
        ]]]]);

    $action = resolve(CreateCustomJobVacancyAction::class);
    $result = $action->handle('Job description text', $this->user);

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

    Http::fakeSequence()
        ->push(['choices' => [['message' => [
            'content' => json_encode([
                'title' => 'Dev', 'company' => 'C', 'description' => 'd',
                'location' => 'R', 'employment_type' => 'Full-time',
                'responsibilities' => 'C', 'requirements' => 'P',
                'skills_required' => 'L', 'experience_years_min' => 2,
                'experience_years_max' => 5, 'expected_salary' => '50000', 'category' => 'T',
            ]),
        ]]]]);

    $action = resolve(CreateCustomJobVacancyAction::class);
    $action->handle('Job text', $userWithoutResume);
})->throws(HttpException::class);
