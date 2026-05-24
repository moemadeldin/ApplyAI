<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Actions\CustomJobVacancy\CreateCustomJobVacancyAction;
use App\Actions\CustomJobVacancy\DeleteCustomJobVacancyAction;
use App\Http\Requests\DeleteCustomJobVacancyRequest;
use App\Http\Requests\StoreCustomJobVacancyRequest;
use App\Http\Resources\CustomJobVacancyResource;
use App\Http\Resources\CustomJobVacancyWithResultsResource;
use App\Models\CustomJobVacancy;
use App\Models\User;
use App\Traits\APIResponses;
use App\Utilities\Constants;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

final readonly class CustomJobVacancyController
{
    use APIResponses;

    public function index(#[CurrentUser] User $user, Request $request): AnonymousResourceCollection
    {
        $page = (int) $request->query('page', 1);
        $gen = Cache::get('vacancies:gen:' . $user->id, 0);
        $cacheKey = 'user:vacancies:list:' . $user->id . ':gen:' . $gen . ':page:' . $page;

        $vacancies = Cache::remember($cacheKey, 60, fn () =>
            $user->customJobVacancies()
                ->latest()
                ->paginate(Constants::NUMBER_OF_PAGINATED_JOB_VACANCIES)
        );

        return CustomJobVacancyResource::collection($vacancies);
    }

    public function store(
        StoreCustomJobVacancyRequest $request,
        CreateCustomJobVacancyAction $action,
        #[CurrentUser] User $user
    ): JsonResponse {
        /** @var string $jobText */
        $jobText = $request->validated('job_text');
        $result = $action->handle($jobText, $user);

        Cache::increment('vacancies:gen:' . $user->id);
        Cache::increment('applications:gen:' . $user->id);

        return $this->success(new CustomJobVacancyWithResultsResource($result), 'Job Vacancy Created Successfully.', Response::HTTP_CREATED);
    }

    public function show(CustomJobVacancy $customJobVacancy): JsonResponse
    {
        return $this->success($customJobVacancy, '');
    }

    public function destroy(
        DeleteCustomJobVacancyRequest $request,
        DeleteCustomJobVacancyAction $action,
        CustomJobVacancy $customJobVacancy,
        #[CurrentUser] User $user
    ): Response {
        $action->handle($customJobVacancy);

        Cache::increment('vacancies:gen:' . $user->id);
        Cache::increment('applications:gen:' . $user->id);

        return $this->noContent();
    }
}
