<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Auth;

use App\Actions\Auth\SocialLoginAction;
use App\Traits\APIResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Laravel\Socialite\Facades\Socialite;

final readonly class SocialiteController
{
    use APIResponses;

    private const array SUPPORTED_PROVIDERS = ['google'];

    public function redirect(string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        $url = Socialite::driver($provider)
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return $this->success(['url' => $url], '', Response::HTTP_FOUND);
    }

    public function callback(string $provider, SocialLoginAction $action): JsonResponse
    {
        $this->validateProvider($provider);

        $socialUser = Socialite::driver($provider)
            ->stateless()
            ->user();

        $token = $action->handle($socialUser, $provider);

        return $this->success(['token' => $token], '', Response::HTTP_CREATED);
    }

    private function validateProvider(string $provider): void
    {
        abort_unless(
            in_array($provider, self::SUPPORTED_PROVIDERS, true),
            Response::HTTP_NOT_FOUND,
            sprintf("Provider '%s' is not supported.", $provider)
        );
    }
}
