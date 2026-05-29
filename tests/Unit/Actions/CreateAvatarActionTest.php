<?php

declare(strict_types=1);

use App\Actions\CreateAvatarAction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('s3');
});

describe('CreateAvatarAction', function (): void {
    it('creates avatar for user without profile', function (): void {
        $user = User::factory()->create();
        $avatar = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $action = resolve(CreateAvatarAction::class);
        $action->handle($user, $avatar);

        $user->load('profile');

        expect($user->profile)->not->toBeNull();
        expect($user->profile->avatar)->toContain('profile_pictures/');
    });

    it('updates existing avatar', function (): void {
        $user = User::factory()->create();
        $user->profile()->create(['avatar' => 'profile_pictures/old.jpg']);
        $avatar = UploadedFile::fake()->image('new-avatar.jpg', 100, 100);

        $action = resolve(CreateAvatarAction::class);
        $action->handle($user, $avatar);

        $user->load('profile');

        expect($user->profile->avatar)->not->toBe('profile_pictures/old.jpg');
    });
});
