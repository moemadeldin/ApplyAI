<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Description('Creates a new user')]
#[Signature('users:create')]
final class CreateUserCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /** @var array{email: string, password: string} $user */
        $user = [
            'email' => $this->ask('Email of the new user'),
            'password' => $this->secret('Password of the new user'),
        ];

        $validator = Validator::make($user, [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults()
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return;
        }

        User::query()->create([
            'email' => $user['email'],
            'password' => bcrypt($user['password']),
        ]);
        $this->info('User '.$user['email'].' created successfully');
    }
}
