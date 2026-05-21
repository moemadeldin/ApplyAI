<?php

declare(strict_types=1);

use App\Traits\APIResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class APIResponsesTestClass
{
    use APIResponses {
        success as public;
        fail as public;
        noContent as public;
    }
}

beforeEach(function (): void {
    $this->trait = new APIResponsesTestClass;
});

test('success returns 200 with data and message', function (): void {
    $response = $this->trait->success(['key' => 'value'], 'Success message');

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->status())->toBe(Response::HTTP_OK)
        ->and($response->getData(true))->toMatchArray([
            'status' => 'Success',
            'message' => 'Success message',
            'data' => ['key' => 'value'],
        ]);
});

test('success returns custom status code', function (): void {
    $response = $this->trait->success([], 'Created', Response::HTTP_CREATED);

    expect($response->status())->toBe(Response::HTTP_CREATED);
});

test('fail returns 400 with message', function (): void {
    $response = $this->trait->fail('Error message');

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->status())->toBe(Response::HTTP_BAD_REQUEST)
        ->and($response->getData(true))->toMatchArray([
            'status' => 'Failed',
            'message' => 'Error message',
        ]);
});

test('fail returns custom status code', function (): void {
    $response = $this->trait->fail('Not found', Response::HTTP_NOT_FOUND);

    expect($response->status())->toBe(Response::HTTP_NOT_FOUND);
});

test('noContent returns 204 with no content', function (): void {
    $response = $this->trait->noContent();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->status())->toBe(Response::HTTP_NO_CONTENT)
        ->and($response->content())->toBe('');
});
