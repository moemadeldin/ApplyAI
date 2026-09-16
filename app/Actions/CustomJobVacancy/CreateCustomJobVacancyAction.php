<?php

declare(strict_types=1);

namespace App\Actions\CustomJobVacancy;

use App\Enums\MockInterviewStatus;
use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\MockInterview;
use App\Models\MockInterviewQuestion;
use App\Models\User;
use App\Services\EvaluateResumeWithAIService;
use App\Services\GenerateCoverLetterService;
use App\Services\GenerateMockInterviewQAService;
use App\Services\OptimizeResumeService;
use App\Services\ParseJobVacancyService;
use App\Utilities\Constants;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CreateCustomJobVacancyAction
{
    public function __construct(
        private ParseJobVacancyService $parseService,
        private EvaluateResumeWithAIService $evaluateService,
        private GenerateMockInterviewQAService $generateService,
        private OptimizeResumeService $optimizeService,
        private GenerateCoverLetterService $coverLetterService,
    ) {}

    /**
     * @return array{vacancy: CustomJobVacancy, application: CustomJobApplication, mock_interview: ?MockInterview}
     */
    public function handle(string $jobText, User $user, ?string $jobUrl = null): array
    {
        return DB::transaction(function () use ($jobText, $user, $jobUrl): array {

            $user->loadMissing('resume');

            abort_if(
                ! $user->resume || ! $user->resume->extracted_text,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Resume not found or has no extracted text.'
            );

            $resumeText = $user->resume->extracted_text;

            [$parsed, $evaluation] = Concurrency::run([
                fn (): array => $this->parseService->parse($jobText),
                fn (): array => $this->evaluateService->evaluate($resumeText, $jobText),
            ], timeout: $this->taskTimeout());

            $parsed = $this->normalizeParsed($parsed);
            $evaluation = $this->normalizeEvaluation($evaluation);

            [$optimizedResume, $qaList] = Concurrency::run([
                fn (): string => $this->optimizeService->optimize($resumeText, $jobText),
                fn (): array => $this->generateService->generate($resumeText, $jobText),
            ], timeout: $this->taskTimeout());

            $optimizedResume = $this->normalizeResume($optimizedResume);
            $qaList = $this->normalizeQaList($qaList);

            $vacancy = $this->createVacancy($user, $parsed, $jobText, $jobUrl);

            $score = (int) ($evaluation['score'] ?? 0);

            if ($score >= Constants::MINIMUM_SCORE) {
                $coverLetter = $this->coverLetterService->generate($optimizedResume, $jobText);
            } else {
                $optimizedResume = null;
                $coverLetter = null;
                $qaList = [];
            }

            $application = $this->createApplication(
                $user,
                $vacancy,
                $evaluation,
                $score,
                $optimizedResume,
                $coverLetter
            );

            $mockInterview = $this->createMockInterview(
                $score,
                $application,
                $qaList
            );

            return [
                'vacancy' => $vacancy,
                'application' => $application,
                'mock_interview' => $mockInterview,
            ];
        });
    }

    private function taskTimeout(): int
    {
        /** @var int $timeout */
        $timeout = config('concurrency.task_timeout', 360);

        return $timeout;
    }

    /**
     * @return array<string, int|string|null>
     */
    private function normalizeParsed(mixed $value): array
    {
        throw_unless(is_array($value), RuntimeException::class, 'AI parsing produced an unexpected result.');

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && ($item === null || is_int($item) || is_string($item))) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array{score: int, feedback: array{strengths: list<string>, weaknesses: list<string>}, suggestions: string}
     */
    private function normalizeEvaluation(mixed $value): array
    {
        throw_unless(is_array($value), RuntimeException::class, 'AI evaluation produced an unexpected result.');

        $feedback = is_array($value['feedback'] ?? null) ? $value['feedback'] : [];

        $strengths = is_array($feedback['strengths'] ?? null)
            ? array_values(array_filter($feedback['strengths'], is_string(...)))
            : [];

        $weaknesses = is_array($feedback['weaknesses'] ?? null)
            ? array_values(array_filter($feedback['weaknesses'], is_string(...)))
            : [];

        $suggestions = is_string($value['suggestions'] ?? null) ? $value['suggestions'] : '';
        $score = is_int($value['score'] ?? null) ? $value['score'] : 0;

        return [
            'score' => $score,
            'feedback' => [
                'strengths' => $strengths,
                'weaknesses' => $weaknesses,
            ],
            'suggestions' => $suggestions,
        ];
    }

    private function normalizeResume(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private function normalizeQaList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (is_array($item) && is_string($item['question'] ?? null) && is_string($item['answer'] ?? null)) {
                $result[] = [
                    'question' => $item['question'],
                    'answer' => $item['answer'],
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, int|string|null>  $parsed
     */
    private function createVacancy(User $user, array $parsed, ?string $jobText = null, ?string $jobUrl = null): CustomJobVacancy
    {
        return CustomJobVacancy::query()->create([
            'title' => $parsed['title'],
            'company' => $parsed['company'],
            'description' => $parsed['description'],
            'location' => $parsed['location'],
            'employment_type' => $parsed['employment_type'] ?? 'full-time',
            'responsibilities' => $parsed['responsibilities'],
            'requirements' => $parsed['requirements'],
            'skills_required' => $parsed['skills_required'],
            'experience_years_min' => $parsed['experience_years_min'],
            'experience_years_max' => $parsed['experience_years_max'],
            'expected_salary' => $parsed['expected_salary'],
            'category' => $parsed['category'],
            'job_text' => $jobText,
            'job_url' => $jobUrl,
            'user_id' => $user->id,
        ]);
    }

    /**
     * @param  array{score: int, feedback: array{strengths: list<string>, weaknesses: list<string>}, suggestions: string}  $evaluation
     */
    private function createApplication(
        User $user,
        CustomJobVacancy $vacancy,
        array $evaluation,
        int $score,
        ?string $optimizedResume,
        ?string $coverLetter
    ): CustomJobApplication {
        return CustomJobApplication::query()->create([
            'user_id' => $user->id,
            'custom_job_vacancy_id' => $vacancy->id,
            'compatibility_score' => $score,
            'feedback' => $evaluation['feedback'],
            'improvement_suggestions' => $evaluation['suggestions'],
            'optimized_resume' => $optimizedResume,
            'cover_letter' => $coverLetter,
        ]);
    }

    /**
     * @param  list<array{question: string, answer: string}>  $qaList
     */
    private function createMockInterview(
        int $score,
        CustomJobApplication $application,
        array $qaList
    ): ?MockInterview {
        if ($score < Constants::MINIMUM_SCORE) {
            MockInterview::query()->create([
                'application_id' => $application->id,
                'status' => MockInterviewStatus::DISQUALIFIED->value,
            ]);

            return null;
        }

        $mockInterview = MockInterview::query()->create([
            'application_id' => $application->id,
            'status' => MockInterviewStatus::QUALIFIED->value,
        ]);

        $questionsData = collect($qaList)->map(fn ($qa, $index): array => [
            'id' => (string) Str::uuid(),
            'mock_interview_id' => $mockInterview->id,
            'question' => $qa['question'],
            'answer' => $qa['answer'],
            'order' => $index + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all();

        MockInterviewQuestion::query()->insert($questionsData);

        return $mockInterview->load('questions');
    }
}
