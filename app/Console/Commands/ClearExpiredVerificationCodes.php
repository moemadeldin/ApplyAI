<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('clears expired verification code')]
#[Signature('codes:clear')]
final class ClearExpiredVerificationCodes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        User::query()->whereNotNull('verification_code')
            ->where('verification_code_expire_at', '<', now())
            ->update([
                'verification_code' => null,
                'verification_code_expire_at' => null,
            ]);
    }
}
