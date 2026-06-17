<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Utilities\Constants;
use Illuminate\Container\Attributes\CurrentUser;
use InvalidArgumentException;

final readonly class TokenManager
{
    public function createAccessToken(User $user, string $type): string
    {
        $tokenName = match ($type) {
            Constants::REGISTER_TOKEN_TYPE => Constants::REGISTER_TOKEN_NAME,
            Constants::LOGIN_TOKEN_TYPE => Constants::LOGIN_TOKEN_NAME,
            Constants::PASSWORD_RESET_TOKEN_TYPE => Constants::PASSWORD_RESET_TOKEN_NAME,
            Constants::EMAIL_VERIFICATION_TOKEN_TYPE => Constants::EMAIL_VERIFICATION_TOKEN_NAME,
            Constants::SOCIAL_LOGIN_TOKEN_TYPE => Constants::SOCIAL_LOGIN_TOKEN_NAME,
            default => throw new InvalidArgumentException('Invalid token type'),
        };

        return $user->access_token = $user->createToken($tokenName, [$type])->plainTextToken;
    }

    public function deleteAccessToken(#[CurrentUser] User $user): void
    {
        $user->tokens()->delete();
    }
}
