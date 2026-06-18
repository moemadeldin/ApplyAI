<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use SensitiveParameter;

final readonly class ChangePasswordDTO
{
    public function __construct(
        #[SensitiveParameter] public string $newPassword,
        #[SensitiveParameter] public ?string $currentPassword = null,
    ) {}

    /**
     * @param  array{current_password?: mixed, new_password: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var string $newPassword */
        $newPassword = $data['new_password'];

        /** @var string|null $currentPassword */
        $currentPassword = $data['current_password'] ?? null;

        return new self(
            newPassword: $newPassword,
            currentPassword: is_string($currentPassword) ? $currentPassword : null,
        );
    }
}
