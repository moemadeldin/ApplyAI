<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Auth;

use App\Actions\Auth\SocialLoginAction;
use App\Traits\APIResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Laravel\Socialite\Facades\Socialite;

final readonly class SocialiteController
{
    use APIResponses;

    private const array SUPPORTED_PROVIDERS = ['linkedin', 'google'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)
            ->stateless()
            ->redirect();
    }

    public function callback(string $provider, SocialLoginAction $action): RedirectResponse
    {
        $this->validateProvider($provider);

        $socialUser = Socialite::driver($provider)->stateless()->user();

        $user = $action->handle($socialUser, $provider);

        return redirect(
            config('app.frontend_url').'/auth/callback?token='.$user->access_token
        );
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
