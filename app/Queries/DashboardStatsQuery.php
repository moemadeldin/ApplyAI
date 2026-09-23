<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CustomJobApplication;
use App\Models\User;
use App\Utilities\Constants;

final readonly class DashboardStatsQuery
{
    /**
     * @return array{total_success: int, total_failures: int, success_rate_percentage: float}
     */
    public function handle(User $user): array
    {
        $total = CustomJobApplication::query()
            ->where('user_id', $user->id)
            ->count();

        $successful = CustomJobApplication::query()
            ->where('user_id', $user->id)
            ->where('compatibility_score', '>=', Constants::MINIMUM_SCORE)
            ->count();

        $failed = $total - $successful;

        $successRate = $total > 0
            ? round(($successful / $total) * 100, 2)
            : 0.0;

        return [
            'total_success' => $successful,
            'total_failures' => $failed,
            'success_rate_percentage' => $successRate,
        ];
    }
}
