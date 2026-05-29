<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Profile>
 */
final class ProfileFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Profile>
     */
    use RefreshOnCreate;

    /**
     * @var class-string<Profile>
     */
    #[Override]
    protected $model = Profile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'avatar' => $this->faker->image(),
        ];
    }
}
