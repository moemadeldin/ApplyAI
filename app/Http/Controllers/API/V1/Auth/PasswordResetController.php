<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerificationCodeRequest;
use App\Models\User;
use App\Services\PasswordResetService;
use App\Traits\APIResponses;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class PasswordResetController
{
    use APIResponses;

    public function __construct(private PasswordResetService $passwordResetService) {}

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        /** @var string $email */
        $email = $request->validated('email');

        $this->passwordResetService->forgot($email);

        return $this->success(
            [], 'Verification Code Sent Successfully.'
        );
    }

    public function checkCode(VerificationCodeRequest $request): JsonResponse
    {
        /** @var string $email */
        $email = $request->validated('email');
        /** @var string $code */
        $code = $request->validated('code');

        return $this->success(
            $this->passwordResetService->checkCode($email, $code), 'Verification Code is Correct.'
        );
    }

    public function resetPassword(#[CurrentUser] User $user, ResetPasswordRequest $request): JsonResponse
    {
        /** @var string $newPassword */
        $newPassword = $request->validated('new_password');

        return $this->success(
            $this->passwordResetService->reset($user, $newPassword), 'Password has been recovered.'
        );
    }
}
