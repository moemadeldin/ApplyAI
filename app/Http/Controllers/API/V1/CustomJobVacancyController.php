<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Actions\CustomJobVacancy\DeleteCustomJobVacancyAction;
use App\Enums\ProcessingStatus;
use App\Http\Requests\DeleteCustomJobVacancyRequest;
use App\Http\Requests\StoreCustomJobVacancyRequest;
use App\Http\Resources\CustomJobApplicationResource;
use App\Http\Resources\CustomJobVacancyResource;
use App\Jobs\ProcessCustomJobVacancyJob;
use App\Models\CustomJobVacancy;
use App\Models\User;
use App\Services\FetchJobPageService;
use App\Traits\APIResponses;
use App\Utilities\Constants;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class CustomJobVacancyController
{
    use APIResponses;

    public function __construct(private FetchJobPageService $fetchJobPageService) {}

    public function index(#[CurrentUser] User $user, Request $request): AnonymousResourceCollection
    {
        $page = (int) $request->query('page', 1);
        $gen = Cache::get('vacancies:gen:'.$user->id, 0);
        $gen = is_int($gen) ? $gen : 0;

        $cacheKey = 'user:vacancies:list:'.$user->id.':gen:'.$gen.':page:'.$page;

        $vacancies = Cache::remember($cacheKey, 60, fn () => $user->customJobVacancies()
            ->latest()
            ->paginate(Constants::NUMBER_OF_PAGINATED_JOB_VACANCIES)
        );

        return CustomJobVacancyResource::collection($vacancies);
    }

    public function store(
        StoreCustomJobVacancyRequest $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        /** @var string|null $jobUrl */
        $jobUrl = $request->validated('job_url');
        /** @var string|null $jobText */
        $jobText = $request->validated('job_text');

        if ($jobUrl !== null) {
            try {
                $jobText = $this->fetchJobPageService->fetch($jobUrl);
            } catch (RuntimeException $e) {
                return $this->fail($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        throw_if($jobText === null, RuntimeException::class, 'Job text or URL is required.');

        $user->loadMissing('resume');

        if (! $user->resume || ! $user->resume->extracted_text) {
            return $this->fail('Resume not found or has no extracted text.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        [$vacancy, $application] = DB::transaction(function () use ($user, $jobText, $jobUrl): array {
            $vacancy = CustomJobVacancy::query()->create([
                'job_text' => $jobText,
                'job_url' => $jobUrl,
                'user_id' => $user->id,
                'status' => ProcessingStatus::PENDING->value,
            ]);

            $application = $vacancy->customJobApplications()->create([
                'user_id' => $user->id,
                'status' => ProcessingStatus::PENDING->value,
            ]);

            return [$vacancy, $application];
        });

        Cache::increment('vacancies:gen:'.$user->id);
        Cache::increment('applications:gen:'.$user->id);

        ProcessCustomJobVacancyJob::dispatch($vacancy, $application);

        return $this->success([
            'vacancy_id' => $vacancy->id,
            'application_id' => $application->id,
            'status' => ProcessingStatus::PENDING->value,
            'status_url' => route('custom-vacancies.status', $vacancy),
            'estimated_time_seconds' => Constants::ESTIMATED_PROCESSING_TIME_SECONDS,
        ], 'Job vacancy queued for processing.', Response::HTTP_ACCEPTED);
    }

    public function status(CustomJobVacancy $customJobVacancy, #[CurrentUser] User $user): JsonResponse
    {
        abort_if($customJobVacancy->user_id !== $user->id, Response::HTTP_NOT_FOUND);

        $payload = [
            'vacancy_id' => $customJobVacancy->id,
            'status' => $customJobVacancy->status->value,
            'current_step' => $customJobVacancy->current_step,
        ];

        if ($customJobVacancy->status === ProcessingStatus::FAILED) {
            $payload['error'] = $customJobVacancy->error_message;
        }

        if ($customJobVacancy->status === ProcessingStatus::COMPLETED) {
            $application = $customJobVacancy->customJobApplications()
                ->with(['customJobVacancy', 'mockInterview.questions'])
                ->first();

            $payload['application_id'] = $application?->id;
            $payload['vacancy'] = new CustomJobVacancyResource($customJobVacancy);

            if ($application !== null) {
                $payload['application'] = new CustomJobApplicationResource($application);
            }
        }

        return $this->success($payload, '');
    }

    public function show(CustomJobVacancy $customJobVacancy, #[CurrentUser] User $user): JsonResponse
    {
        abort_if($customJobVacancy->user_id !== $user->id, Response::HTTP_NOT_FOUND);

        return $this->success(new CustomJobVacancyResource($customJobVacancy), '');
    }

    public function destroy(
        DeleteCustomJobVacancyRequest $request,
        DeleteCustomJobVacancyAction $action,
        CustomJobVacancy $customJobVacancy,
        #[CurrentUser] User $user
    ): Response {
        abort_if($customJobVacancy->user_id !== $user->id, Response::HTTP_NOT_FOUND);

        $action->handle($customJobVacancy);

        Cache::increment('vacancies:gen:'.$user->id);
        Cache::increment('applications:gen:'.$user->id);

        return $this->noContent();
    }
}
