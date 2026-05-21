<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Storage::fake('public');
});

describe('ProfileController', function (): void {
    it('can get profile', function (): void {
        $user = User::factory()->create();
        $user->profile()->save(Profile::factory()->make());
        $user->resume()->create([
            'name' => 'test-resume.pdf',
            'path' => 'resumes/test-resume.pdf',
            'extracted_text' => 'Test resume',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('me.show'));

        $response->assertOk();
    });

    it('can upload avatar', function (): void {
        $user = User::factory()->create();
        $user->resume()->create([
            'name' => 'test-resume.pdf',
            'path' => 'resumes/test-resume.pdf',
            'extracted_text' => 'Test resume',
        ]);

        Sanctum::actingAs($user);

        $avatar = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->postJson(route('profile.store'), [
            'avatar' => $avatar,
        ]);

        $response->assertOk();
    });

    it('requires authentication to get profile', function (): void {
        $response = $this->getJson(route('me.show'));

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    });

    it('requires authentication to upload avatar', function (): void {
        $response = $this->postJson(route('profile.store'), []);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    });

    it('requires authentication to delete account', function (): void {
        $response = $this->deleteJson(route('profile.destroy'), []);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    });
});

// describe('ProfilePasswordController', function (): void {
//     it('requires authentication to change password', function (): void {
//         $response = $this->postJson(route('profile.password'), [
//             'current_password' => 'password123456',
//             'new_password' => 'newpassword123456',
//             'new_password_confirmation' _pdf=> 'newpassword123456',
//         ]);

//         $response->assertStatus(Response::HTTP_UNAUTHORIZED);
//     });
// });
