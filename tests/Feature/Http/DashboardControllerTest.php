<?php

declare(strict_types=1);

use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\User;
use App\Utilities\Constants;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;

describe('DashboardController', function (): void {
    it('returns all applications as successes when scores meet the minimum', function (): void {
        $user = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($user)->create();
        CustomJobApplication::factory()
            ->count(3)
            ->for($user)
            ->for($vacancy)
            ->create(['compatibility_score' => 85]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('dashboard.stats'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.total_success', 3)
            ->assertJsonPath('data.total_failures', 0)
            ->assertJsonPath('data.success_rate_percentage', 100);
    });

    it('calculates success and failure counts with mixed scores', function (): void {
        $user = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($user)->create();
        CustomJobApplication::factory()
            ->count(2)
            ->for($user)
            ->for($vacancy)
            ->create(['compatibility_score' => 85]);
        CustomJobApplication::factory()
            ->for($user)
            ->for($vacancy)
            ->create(['compatibility_score' => Constants::MINIMUM_SCORE - 1]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('dashboard.stats'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.total_success', 2)
            ->assertJsonPath('data.total_failures', 1)
            ->assertJsonPath('data.success_rate_percentage', 66.67);
    });

    it('treats a score equal to the minimum as a success', function (): void {
        $user = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($user)->create();
        CustomJobApplication::factory()
            ->for($user)
            ->for($vacancy)
            ->create(['compatibility_score' => Constants::MINIMUM_SCORE]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('dashboard.stats'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.total_success', 1)
            ->assertJsonPath('data.total_failures', 0);
    });

    it('does not count other users applications', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $vacancy = CustomJobVacancy::factory()->for($otherUser)->create();
        CustomJobApplication::factory()
            ->for($otherUser)
            ->for($vacancy)
            ->create(['compatibility_score' => 95]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('dashboard.stats'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.total_success', 0)
            ->assertJsonPath('data.total_failures', 0)
            ->assertJsonPath('data.success_rate_percentage', 0);
    });

    it('returns zeroed stats for a user with no applications', function (): void {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('dashboard.stats'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.total_success', 0)
            ->assertJsonPath('data.total_failures', 0)
            ->assertJsonPath('data.success_rate_percentage', 0);
    });

    it('requires authentication', function (): void {
        $this->getJson(route('dashboard.stats'))
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    });
});
