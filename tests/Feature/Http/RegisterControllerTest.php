<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Response;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;

beforeEach(function (): void {
    Turnstile::fake();
});

it('can register a user', function (): void {
    $response = $this->postJson(route('register.store'), [
        'email' => 'johndoe@gmail.com',
        'password' => 'password123456',
        'password_confirmation' => 'password123456',
        'cf-turnstile-response' => Turnstile::dummy(),
    ]);

    $response->assertStatus(Response::HTTP_CREATED);

    $user = User::whereEmail('johndoe@gmail.com')->first();

    expect($user)
        ->not->toBeNull()
        ->and($user->email)->toBe('johndoe@gmail.com')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('validates request fields', function (): void {
    $response = $this->postJson(route('register.store'), []);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['email', 'password', 'cf-turnstile-response']);
});
it('validates email format', function (): void {
    $response = $this->postJson(route('register.store'), [
        'email' => 'johndoe',
        'password' => 'password123456',
        'password_confirmation' => 'password123456',
        'cf-turnstile-response' => Turnstile::dummy(),
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['email']);
});
it('validates email format is actual email', function (): void {
    $response = $this->postJson(route('register.store'), [
        'email' => 'johndoe@example.com',
        'password' => 'password123456',
        'password_confirmation' => 'password123456',
        'cf-turnstile-response' => Turnstile::dummy(),
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['email']);
});
it('validates email is unique', function (): void {
    $user = User::factory()->create([
        'email' => 'johndoe@gmail.com',
    ]);

    $response = $this->postJson(route('register.store'), [
        'email' => 'johndoe@gmail.com',
        'password' => 'password123456',
        'password_confirmation' => 'password123456',
        'cf-turnstile-response' => Turnstile::dummy(),
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['email']);
});
it('validates password rules', function (): void {

    $response = $this->postJson(route('register.store'), [
        'email' => 'johndoe@gmail.com',
        'password' => '0123456',
        'password_confirmation' => '0123456',
        'cf-turnstile-response' => Turnstile::dummy(),
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['password']);
});
it('validates password confirmation', function (): void {

    $response = $this->postJson(route('register.store'), [
        'email' => 'johndoe@gmail.com',
        'password' => 'password123456',
        'password_confirmation' => 'password12345',
        'cf-turnstile-response' => Turnstile::dummy(),
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['password']);
});
