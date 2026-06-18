<?php

declare(strict_types=1);

use App\Models\User;
use App\Utilities\Constants;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;

beforeEach(function (): void {
    Turnstile::fake();
});

it('sends verification code for valid email', function (): void {
    $user = User::factory()->create();

    $response = $this->postJson(route('forgot.store'), [
        'email' => $user->email,
        'cf-turnstile-response' => Turnstile::dummy(),
    ]);

    $response->assertOk();
});

it('validates email format for forgot password', function (): void {
    $response = $this->postJson(route('forgot.store'), [
        'email' => 'invalid-email',
        'cf-turnstile-response' => Turnstile::dummy(),
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['email']);
});

it('verifies code with valid code', function (): void {
    $user = User::factory()->create();
    $user->update([
        'verification_code' => '123456',
        'verification_code_expire_at' => now()->addMinutes(Constants::EXPIRATION_VERIFICATION_CODE_TIME_IN_MINUTES),
    ]);

    $response = $this->postJson(route('verify.code'), [
        'email' => $user->email,
        'code' => '123456',
    ]);

    $response->assertOk();
});

it('fails with invalid code', function (): void {
    $user = User::factory()->create();
    $user->update([
        'verification_code' => '123456',
        'verification_code_expire_at' => now()->addMinutes(Constants::EXPIRATION_VERIFICATION_CODE_TIME_IN_MINUTES),
    ]);

    $response = $this->postJson(route('verify.code'), [
        'email' => $user->email,
        'code' => '000000',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
});

it('resets password successfully', function (): void {
    $user = User::factory()->create();
    $user->update([
        'verification_code' => '123456',
        'verification_code_expire_at' => now()->addMinutes(Constants::EXPIRATION_VERIFICATION_CODE_TIME_IN_MINUTES),
    ]);

    Sanctum::actingAs($user, ['Reset']);

    $response = $this->postJson(route('reset.password'), [
        'new_password' => 'NewPassword123!',
        'new_password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertOk();

    $user->refresh();
    expect(password_verify('NewPassword123!', (string) $user->password))->toBeTrue();
});

it('validates new password requirements', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['Reset']);

    $response = $this->postJson(route('reset.password'), [
        'new_password' => 'weak',
        'new_password_confirmation' => 'weak',
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['new_password']);
});

it('validates password confirmation matches', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['Reset']);

    $response = $this->postJson(route('reset.password'), [
        'new_password' => 'Password123!',
        'new_password_confirmation' => 'DifferentPassword123!',
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['new_password']);
});

it('requires authentication to reset password', function (): void {
    $response = $this->postJson(route('reset.password'), [
        'new_password' => 'Password123!',
        'new_password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});
