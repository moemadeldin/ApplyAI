<?php

declare(strict_types=1);

use App\Http\Resources\MockInterviewResource;
use App\Models\MockInterview;
use App\Models\MockInterviewQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resource transforms with questions', function (): void {
    $mockInterview = MockInterview::factory()->create();
    MockInterviewQuestion::factory()->count(2)->sequence(
        ['order' => 1, 'question' => 'Q1?', 'answer' => 'A1'],
        ['order' => 2, 'question' => 'Q2?', 'answer' => 'A2'],
    )->create(['mock_interview_id' => $mockInterview->id]);

    $resource = new MockInterviewResource($mockInterview->load('questions'));
    $data = $resource->toArray(request());

    expect($data)
        ->toHaveKey('id', $mockInterview->id)
        ->toHaveKey('questions')
        ->and($data['questions'])->toHaveCount(2)
        ->and($data['questions'][0])->toMatchArray([
            'order' => 1,
            'question' => 'Q1?',
            'answer' => 'A1',
        ])
        ->and($data['questions'][1])->toMatchArray([
            'order' => 2,
            'question' => 'Q2?',
            'answer' => 'A2',
        ])
        ->and($data)->toHaveKey('created_at');
});

test('resource transforms without questions', function (): void {
    $mockInterview = MockInterview::factory()->create();

    $resource = new MockInterviewResource($mockInterview->load('questions'));
    $data = $resource->toArray(request());

    expect($data)
        ->toHaveKey('id', $mockInterview->id)
        ->toHaveKey('questions')
        ->toHaveKey('created_at');
    expect($data['questions'])->toBeEmpty();
});

test('json structure constant matches expected keys', function (): void {
    expect(MockInterviewResource::JSON_STRUCTURE)->toMatchArray([
        'id',
        'questions' => ['order', 'question', 'answer'],
        'created_at',
    ]);
});
