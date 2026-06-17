<?php

declare(strict_types=1);

use App\Actions\Auth\SocialLoginAction;
use App\Enums\Status;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Symfony\Component\HttpKernel\Exception\HttpException;

describe('SocialLoginAction', function (): void {
    it('creates a new user from social login', function (): void {
        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google_123');
        $socialUser->shouldReceive('getEmail')->andReturn('new@example.com');
        $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        $action = resolve(SocialLoginAction::class);
        $token = $action->handle($socialUser, 'google');

        expect($token)->toBeString();
        expect($token)->not->toBeEmpty();

        $user = User::query()
            ->where('provider', 'google')
            ->where('provider_id', 'google_123')
            ->first();

        expect($user)->not->toBeNull();
        expect($user->email)->toBe('new@example.com');
        expect($user->provider)->toBe('google');
        expect($user->provider_id)->toBe('google_123');
        expect($user->email_verified_at)->not->toBeNull();
        expect($user->status)->toBe(Status::ACTIVE);
        expect($user->profile)->not->toBeNull();
        expect($user->profile->avatar)->toBe('https://example.com/avatar.jpg');
    });

    it('returns existing user matched by provider and provider_id', function (): void {
        $existing = User::factory()->create([
            'provider' => 'google',
            'provider_id' => 'google_123',
            'email' => 'existing@example.com',
        ]);

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google_123');
        $socialUser->shouldReceive('getEmail')->andReturn('existing@example.com');
        $socialUser->shouldReceive('getAvatar')->andReturn(null);

        $action = resolve(SocialLoginAction::class);
        $token = $action->handle($socialUser, 'google');

        expect($token)->toBeString();
        expect($token)->not->toBeEmpty();

        $existing->refresh();
        expect($existing->email)->toBe('existing@example.com');
    });

    it('links provider to existing user matched by email', function (): void {
        $existing = User::factory()->create([
            'email' => 'match@example.com',
            'provider' => null,
            'provider_id' => null,
        ]);

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google_789');
        $socialUser->shouldReceive('getEmail')->andReturn('match@example.com');
        $socialUser->shouldReceive('getAvatar')->andReturn(null);

        $action = resolve(SocialLoginAction::class);
        $token = $action->handle($socialUser, 'google');

        expect($token)->toBeString();
        expect($token)->not->toBeEmpty();

        $existing->refresh();
        expect($existing->provider)->toBe('google');
        expect($existing->provider_id)->toBe('google_789');
    });

    it('syncs email_verified_at for unverified existing user', function (): void {
        $existing = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
        ]);

        expect($existing->email_verified_at)->toBeNull();

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google_verified');
        $socialUser->shouldReceive('getEmail')->andReturn('unverified@example.com');
        $socialUser->shouldReceive('getAvatar')->andReturn(null);

        $action = resolve(SocialLoginAction::class);
        $token = $action->handle($socialUser, 'google');

        expect($token)->toBeString();
        expect($token)->not->toBeEmpty();

        $existing->refresh();
        expect($existing->email_verified_at)->not->toBeNull();
    });

    it('syncs avatar when existing user has no avatar', function (): void {
        $existing = User::factory()->create([
            'email' => 'noavatar@example.com',
        ]);

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google_avatar');
        $socialUser->shouldReceive('getEmail')->andReturn('noavatar@example.com');
        $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/new-avatar.jpg');

        $action = resolve(SocialLoginAction::class);
        $token = $action->handle($socialUser, 'google');

        expect($token)->toBeString();
        expect($token)->not->toBeEmpty();

        $existing->refresh();
        expect($existing->profile->avatar)->toBe('https://example.com/new-avatar.jpg');
    });

    it('does not overwrite existing avatar', function (): void {
        $existing = User::factory()->create([
            'email' => 'hasavatar@example.com',
            'provider' => 'google',
            'provider_id' => 'google_has_avatar',
        ]);
        $existing->profile()->create(['avatar' => 'https://example.com/old-avatar.jpg']);

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google_has_avatar');
        $socialUser->shouldReceive('getEmail')->andReturn('hasavatar@example.com');
        $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/new-avatar.jpg');

        $action = resolve(SocialLoginAction::class);
        $token = $action->handle($socialUser, 'google');

        expect($token)->toBeString();
        expect($token)->not->toBeEmpty();

        $existing->refresh();
        expect($existing->profile->avatar)->toBe('https://example.com/old-avatar.jpg');
    });

    it('creates user even when email is null', function (): void {
        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google_noemail');
        $socialUser->shouldReceive('getEmail')->andReturn(null);
        $socialUser->shouldReceive('getAvatar')->andReturn(null);

        $action = resolve(SocialLoginAction::class);
        $token = $action->handle($socialUser, 'google');

        expect($token)->toBeString();
        expect($token)->not->toBeEmpty();

        $user = User::query()
            ->where('provider', 'google')
            ->where('provider_id', 'google_noemail')
            ->first();

        expect($user)->not->toBeNull();
        expect($user->email)->toBeNull();
        expect($user->provider)->toBe('google');
        expect($user->provider_id)->toBe('google_noemail');
        expect($user->profile)->not->toBeNull();
    });

    it('throws for inactive user', function (): void {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'status' => Status::INACTIVE,
            'provider' => 'google',
            'provider_id' => 'google_inactive',
        ]);

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google_inactive');
        $socialUser->shouldReceive('getEmail')->andReturn('inactive@example.com');
        $socialUser->shouldReceive('getAvatar')->andReturn(null);

        $action = resolve(SocialLoginAction::class);
        $action->handle($socialUser, 'google');
    })->throws(HttpException::class, 'Authentication error.');
});
