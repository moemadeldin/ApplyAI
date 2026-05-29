<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Resume;
use App\Models\User;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Resume>
 */
final class ResumeFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Resume>
     */
    use RefreshOnCreate;

    /**
     * @var class-string<Resume>
     */
    #[Override]
    protected $model = Resume::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->name(),
            'path' => 'resumes/'.$this->faker->uuid().'pdf',
            'extracted_text' => $this->faker->text(),
        ];
    }
}
