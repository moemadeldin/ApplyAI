<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MockInterview;
use App\Models\MockInterviewQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<MockInterviewQuestion>
 */
final class MockInterviewQuestionFactory extends Factory
{
    #[Override]
    protected $model = MockInterviewQuestion::class;

    public function definition(): array
    {
        return [
            'mock_interview_id' => MockInterview::factory(),
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'order' => 1,
        ];
    }
}
