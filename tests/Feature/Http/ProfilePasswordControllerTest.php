<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('change password with correct current', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->patchJson(route('profile.password'), [
        'current_password' => 'password',
        'new_password' => 'NewPassword123!',
        'new_password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertOk();
});

test('social login user can set password without current password', function (): void {
    $user = User::factory()->create([
        'password' => null,
        'provider' => 'google',
        'provider_id' => '12345',
    ]);

    Sanctum::actingAs($user);

    $response = $this->patchJson(route('profile.password'), [
        'new_password' => 'NewPassword123!',
        'new_password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertOk();
});
