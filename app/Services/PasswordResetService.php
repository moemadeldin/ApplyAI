<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\PasswordVerificationCodeSent;
use App\Exceptions\AuthException;
use App\Models\User;
use App\Utilities\Constants;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use SensitiveParameter;

final readonly class PasswordResetService
{
    public function __construct(private TokenManager $tokenManager, private UserValidator $userValidator) {}

    public function forgot(string $email): void
    {
        /** @var User $user */
        $user = User::query()->whereEmail($email)->first();

        if (! $user || ! $user->isActive()) {
            return;
        }

        $this->updateUserWithCodeAndToken($user);

    }

    public function checkCode(string $email, string $verificationCode): User
    {
        /** @var User $user */
        $user = User::query()->whereEmail($email)->first();
        throw_unless($user, AuthException::class, 'Invalid credentials.', Response::HTTP_BAD_REQUEST);

        $this->userValidator->validateVerificationCode($user, $verificationCode);

        $this->createResetToken($user);

        return $user;
    }

    public function reset(User $user, #[SensitiveParameter] string $newPassword): User
    {
        $user->update([
            'verification_code' => null,
            'verification_code_expire_at' => null,
            'password' => $newPassword,
        ]);
        $this->tokenManager->deleteAccessToken($user);

        return $user;
    }

    private function updateUserWithCodeAndToken(User $user): void
    {
        $user->update([
            'verification_code' => $this->generateRandomVerificationCode(),
            'verification_code_expire_at' => Date::now()->addMinutes(Constants::EXPIRATION_VERIFICATION_CODE_TIME_IN_MINUTES),
        ]);
        $this->tokenManager->createAccessToken($user, Constants::PASSWORD_RESET_TOKEN_TYPE);

        /** @var string $email */
        $email = $user->email;
        $code = (string) $user->verification_code;

        event(new PasswordVerificationCodeSent($email, $code));
    }

    private function createResetToken(User $user): void
    {
        $this->tokenManager->createAccessToken($user, Constants::PASSWORD_RESET_TOKEN_TYPE);
    }

    private function generateRandomVerificationCode(): int
    {
        return random_int(Constants::MIN_VERIFICATION_CODE, Constants::MAX_VERIFICATION_CODE);
    }
}
