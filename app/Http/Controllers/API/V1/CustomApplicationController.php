<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\CustomJobApplicationOwnershipRequest;
use App\Http\Resources\CustomJobApplicationResource;
use App\Http\Resources\JobApplicationListResource;
use App\Models\CustomJobApplication;
use App\Models\User;
use App\Queries\UserCustomApplicationQuery;
use App\Traits\APIResponses;
use App\Utilities\Constants;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

final readonly class CustomApplicationController
{
    use APIResponses;

    public function __construct(
        private UserCustomApplicationQuery $query
    ) {}

    public function index(#[CurrentUser] User $user, Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', Constants::NUMBER_OF_PAGINATED_JOB_APPLICATIONS);

        /** @var array{status?: string} $filters */
        $filters = $request->only(['status']);

        $page = (int) $request->query('page', 1);
        $gen = Cache::get('applications:gen:'.$user->id, 0);
        $gen = is_int($gen) ? $gen : 0;

        $cacheKey = 'user:applications:list:'.$user->id.':gen:'.$gen.':page:'.$page.':status:'.($filters['status'] ?? 'all');

        $applications = Cache::remember($cacheKey, 60, fn () => $this->query->builder($filters, $user)->paginate($perPage)
        );

        return JobApplicationListResource::collection($applications);
    }

    public function show(
        CustomJobApplicationOwnershipRequest $request,
        CustomJobApplication $customApplication
    ): JsonResponse {
        $cacheKey = 'user:application:show:'.$customApplication->id;

        $data = Cache::remember($cacheKey, 300, function () use ($customApplication): CustomJobApplicationResource {
            $customApplication->load(['customJobVacancy', 'mockInterview']);

            return new CustomJobApplicationResource($customApplication);
        });

        return $this->success($data, 'Application details retrieved successfully');
    }
}
