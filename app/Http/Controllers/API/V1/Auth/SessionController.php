<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Auth;

use App\Actions\Auth\LoginAction;
use App\DTOs\Auth\LoginDTO;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Services\TokenManager;
use App\Traits\APIResponses;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

final readonly class SessionController
{
    use APIResponses;

    public function __construct(private TokenManager $tokenManager) {}

    public function show(#[CurrentUser] User $user): JsonResponse
    {
        $profile = Cache::remember('user:profile:' . $user->id, 300, fn () => new ProfileResource($user));

        return $this->success($profile, '');
    }

    public function store(LoginRequest $request, LoginAction $action): JsonResponse
    {

        /** @var array<string, string> $data */
        $data = $request->validated();

        return $this->success(
            new LoginResource(
                $action->handle(LoginDTO::fromArray($data))
            ),
            'User Logged in Successfully.'
        );

    }

    public function destroy(#[CurrentUser] User $user): Response
    {
        $this->tokenManager->deleteAccessToken($user);

        return $this->noContent();
    }
}
