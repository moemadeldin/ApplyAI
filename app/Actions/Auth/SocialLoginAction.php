<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\Status;
use App\Models\User;
use App\Services\TokenManager;
use App\Utilities\Constants;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final readonly class SocialLoginAction
{
    public function __construct(
        private TokenManager $tokenManager,
    ) {}

    public function handle(SocialiteUser $socialUser, string $provider): User
    {
        $user = DB::transaction(fn (): User => $this->resolveUser($socialUser, $provider));

        abort_if($user->status !== Status::ACTIVE, Response::HTTP_FORBIDDEN, 'Authentication error.');

        $this->tokenManager->createAccessToken($user, Constants::SOCIAL_LOGIN_TOKEN_TYPE);

        return $user;
    }

    private function resolveUser(SocialiteUser $socialUser, string $provider): User
    {
        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($user === null && $socialUser->getEmail() !== null) {
            $user = User::query()->whereEmail($socialUser->getEmail())->first();
        }

        if ($user === null) {
            return $this->createUser($socialUser, $provider);
        }

        $this->syncUser($user, $socialUser, $provider);

        return $user;
    }

    private function createUser(SocialiteUser $socialUser, string $provider): User
    {
        $user = User::query()->create([
            'email' => $socialUser->getEmail(),
            'email_verified_at' => now(),
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'avatar' => $socialUser->getAvatar(),
            'status' => Status::ACTIVE,
        ]);

        $user->profile()->create([
            'avatar' => Constants::DEFAULT_PROFILE_PICTURE_PATH,
        ]);

        return $user;
    }

    private function syncUser(User $user, SocialiteUser $socialUser, string $provider): void
    {
        $updates = [];

        if ($user->provider !== $provider || $user->provider_id !== $socialUser->getId()) {
            $updates['provider'] = $provider;
            $updates['provider_id'] = $socialUser->getId();
        }

        if ($user->email_verified_at === null) {
            $updates['email_verified_at'] = now();
        }

        if ($user->avatar === null && $socialUser->getAvatar() !== null) {
            $updates['avatar'] = $socialUser->getAvatar();
        }

        if ($updates !== []) {
            $user->updateQuietly($updates);
        }
    }
}
