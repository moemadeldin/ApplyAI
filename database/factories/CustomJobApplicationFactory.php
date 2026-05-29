<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CustomJobApplication;
use App\Models\CustomJobVacancy;
use App\Models\User;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CustomJobApplication>
 */
final class CustomJobApplicationFactory extends Factory
{
    /** @use RefreshOnCreate<CustomJobApplication> */
    use RefreshOnCreate;

    #[Override]
    protected $model = CustomJobApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'custom_job_vacancy_id' => CustomJobVacancy::factory(),
            'compatibility_score' => $this->faker->numberBetween(50, 95),
        ];
    }
}
