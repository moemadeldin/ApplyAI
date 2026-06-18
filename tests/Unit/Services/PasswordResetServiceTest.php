<?php

declare(strict_types=1);

use App\Enums\Status;
use App\Events\PasswordVerificationCodeSent;
use App\Models\User;
use App\Services\PasswordResetService;
use App\Services\TokenManager;
use App\Services\UserValidator;
use App\Utilities\Constants;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->tokenManager = new TokenManager();
    $this->userValidator = new UserValidator();

    $this->service = new PasswordResetService(
        $this->tokenManager,
        $this->userValidator
    );
});

test('forgot sends verification code and creates token', function (): void {
    Event::fake(PasswordVerificationCodeSent::class);

    $user = User::factory()->create(['status' => Status::ACTIVE]);

    $this->service->forgot($user->email);

    $user->refresh();

    expect($user->verification_code)->not->toBeNull();
    expect($user->verification_code_expire_at)->not->toBeNull();

    Event::assertDispatched(PasswordVerificationCodeSent::class);

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);
});

test('checkCode validates code and creates token', function (): void {
    $user = User::factory()->create([
        'status' => Status::ACTIVE,
        'verification_code' => 123456,
    ]);

    $result = $this->service->checkCode($user->email, '123456');

    expect($result->id)->toBe($user->id);

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);
});

test('reset updates password and clears verification code', function (): void {
    $user = User::factory()->create([
        'status' => Status::ACTIVE,
        'password' => 'oldpassword',
        'verification_code' => 123456,
        'verification_code_expire_at' => now()->addMinutes(Constants::EXPIRATION_VERIFICATION_CODE_TIME_IN_MINUTES),
    ]);

    $user->createToken(Constants::PASSWORD_RESET_TOKEN_NAME);

    $result = $this->service->reset($user, 'newpassword');

    expect($result->verification_code)->toBeNull();
    expect($result->verification_code_expire_at)->toBeNull();

    expect(Hash::check('newpassword', $result->password))->toBeTrue();

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);
});
