<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Models\User;
use App\Queries\DashboardStatsQuery;
use App\Traits\APIResponses;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class DashboardController
{
    use APIResponses;

    public function __invoke(
        #[CurrentUser] User $user,
        DashboardStatsQuery $query,
    ): JsonResponse {
        return $this->success($query->handle($user), '');
    }
}
