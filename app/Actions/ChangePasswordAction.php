<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\Auth\ChangePasswordDTO;
use App\Exceptions\AuthException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final readonly class ChangePasswordAction
{
    public function handle(User $user, ChangePasswordDTO $dto): User
    {
        if ($dto->currentPassword !== null) {
            throw_unless(Hash::check($dto->currentPassword, (string) $user->password), AuthException::class, 'The current password is incorrect.');
        }

        throw_if($user->password !== null && Hash::check($dto->newPassword, (string) $user->password), AuthException::class, 'You cannot make the new password as the current one.');

        $user->update(['password' => $dto->newPassword]);
        $user->tokens()->delete();

        return $user;
    }
}
