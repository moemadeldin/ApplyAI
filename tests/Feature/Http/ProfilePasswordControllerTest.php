<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('change password with correct current', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson(route('profile.password'), [
        'current_password' => 'password',
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123',
    ]);

    $response->assertOk();
});
