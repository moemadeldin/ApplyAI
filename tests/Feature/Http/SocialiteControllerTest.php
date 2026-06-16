<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

it('redirects to supported provider', function (): void {
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth?redirect_uri=...'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.provider.redirect', ['provider' => 'google']))
        ->assertStatus(302);
});

it('returns 404 for unsupported provider on redirect', function (): void {
    $this->get(route('auth.provider.redirect', ['provider' => 'github']))
        ->assertNotFound();
});

it('handles callback and redirects to frontend with token', function (): void {
    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('google_123');
    $socialUser->shouldReceive('getEmail')->andReturn('test@example.com');
    $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('user')->once()->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    config(['app.frontend_url' => 'http://localhost:3000']);

    $response = $this->post(route('auth.provider.callback', ['provider' => 'google']));

    $response->assertRedirect();
    $response->assertRedirectContains('http://localhost:3000/auth/callback');

    $user = User::query()
        ->where('provider', 'google')
        ->where('provider_id', 'google_123')
        ->first();

    expect($user)->not->toBeNull();
    expect($user->email)->toBe('test@example.com');
    expect($user->profile)->not->toBeNull();
});

it('returns 404 for unsupported provider on callback', function (): void {
    $this->post(route('auth.provider.callback', ['provider' => 'github']))
        ->assertNotFound();
});

it('links provider to existing user by email', function (): void {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('google_456');
    $socialUser->shouldReceive('getEmail')->andReturn('existing@example.com');
    $socialUser->shouldReceive('getAvatar')->andReturn(null);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->once()->andReturnSelf();
    $provider->shouldReceive('user')->once()->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    config(['app.frontend_url' => 'http://localhost:3000']);

    $this->post(route('auth.provider.callback', ['provider' => 'google']))
        ->assertRedirect();

    $user->refresh();

    expect($user->provider)->toBe('google');
    expect($user->provider_id)->toBe('google_456');
});
