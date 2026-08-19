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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            $parsed = $this->parseService->parse($jobText);
            $vacancy = $this->createVacancy($user, $parsed, $jobText, $jobUrl);

            $user->loadMissing('resume');

            abort_if(
                ! $user->resume || ! $user->resume->extracted_text,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Resume not found or has no extracted text.'
            );

            $resumeText = $user->resume->extracted_text;

            $evaluation = $this->evaluateService->evaluate($resumeText, $jobText);
            $score = (int) ($evaluation['score'] ?? 0);

            if ($score >= Constants::MINIMUM_SCORE) {
                $optimizedResume = $this->optimizeService->optimize($resumeText, $jobText);
                $coverLetter = $this->coverLetterService->generate($optimizedResume, $jobText);
            } else {
                $optimizedResume = null;
                $coverLetter = null;
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
                $resumeText,
                $jobText,
                $application
            );

            return [
                'vacancy' => $vacancy,
                'application' => $application,
                'mock_interview' => $mockInterview,
            ];
        });
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

    private function createMockInterview(
        int $score,
        string $resumeText,
        string $jobText,
        CustomJobApplication $application
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

        $qaList = $this->generateService->generate($resumeText, $jobText);

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
