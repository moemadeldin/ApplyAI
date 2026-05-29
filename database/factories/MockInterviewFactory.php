<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CustomJobApplication;
use App\Models\MockInterview;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<MockInterview>
 */
final class MockInterviewFactory extends Factory
{
    /** @use RefreshOnCreate<MockInterview> */
    use RefreshOnCreate;

    #[Override]
    protected $model = MockInterview::class;

    public function definition(): array
    {
        return [
            'application_id' => CustomJobApplication::factory(),
            'status' => 'qualified',
        ];
    }
}
