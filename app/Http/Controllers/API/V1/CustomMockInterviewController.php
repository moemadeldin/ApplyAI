<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\CustomJobApplicationOwnershipRequest;
use App\Http\Resources\InterviewQuestionResource;
use App\Models\CustomJobApplication;
use App\Traits\APIResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final readonly class CustomMockInterviewController
{
    use APIResponses;

    public function __invoke(CustomJobApplicationOwnershipRequest $request, CustomJobApplication $customApplication): JsonResponse
    {
        $questions = Cache::remember('user:application:mock_questions:' . $customApplication->id, 86400, function () use ($customApplication) {
            $customApplication->load('mockInterview');

            return $customApplication->mockInterview
                ?->questions()
                ->orderBy('order')
                ->get()
            ?? collect();
        });

        if ($questions->isEmpty()) {
            return $this->success([], 'No mock interview questions available.');
        }

        return $this->success(InterviewQuestionResource::collection($questions), 'Expected Interview Questions.');
    }
}
