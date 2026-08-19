<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Models\CustomJobVacancy;
use App\Models\User;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CustomJobVacancy>
 */
final class CustomJobVacancyFactory extends Factory
{
    /** @use RefreshOnCreate<CustomJobVacancy> */
    use RefreshOnCreate;

    #[Override]
    protected $model = CustomJobVacancy::class;

    public function definition(): array
    {
        $minExp = $this->faker->numberBetween(1, 2);

        return [
            'user_id' => User::factory(),
            'category' => $this->faker->randomElement(['Technology', 'Marketing', 'Sales', 'Finance']),
            'company' => $this->faker->company(),
            'title' => $this->faker->jobTitle(),
            'description' => $this->faker->text(200),
            'job_text' => $this->faker->text(200),
            'job_url' => $this->faker->url(),
            'location' => $this->faker->city(),
            'expected_salary' => $this->faker->numberBetween(30000, 120000),
            'employment_type' => EmploymentType::FULL_TIME->value,
            'responsibilities' => $this->faker->text(200),
            'requirements' => $this->faker->text(200),
            'skills_required' => $this->faker->text(200),
            'experience_years_min' => $minExp,
            'experience_years_max' => $this->faker->numberBetween($minExp, 10),
            'nice_to_have' => $this->faker->text(100),
        ];
    }
}
