<?php

declare(strict_types=1);

use App\Http\Controllers\API\V1\Auth\PasswordResetController;
use App\Http\Controllers\API\V1\Auth\RegisterController;
use App\Http\Controllers\API\V1\Auth\SessionController;
use App\Http\Controllers\API\V1\Auth\SocialiteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest', 'throttle:5,1'])->group(function (): void {
    Route::post('/register', RegisterController::class)
        ->name('register.store');

    Route::post('/login', [SessionController::class, 'store'])
        ->name('login.store');

    Route::controller(PasswordResetController::class)->group(function (): void {
        Route::post('/forgot-password', 'forgotPassword')
            ->name('forgot.store');
        Route::post('/verify-code', 'checkCode')
            ->name('verify.code');
    });
    Route::controller(SocialiteController::class)->group(function (): void {
        Route::get('/auth/{provider}/redirect', 'redirect')
            ->name('auth.provider.redirect');
        Route::post('/auth/{provider}/callback', 'callback')
            ->name('auth.provider.callback');
    });
});
