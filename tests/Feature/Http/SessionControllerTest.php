<?php

declare(strict_types=1);

use App\Enums\Status;
use App\Http\Resources\LoginResource;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('can login user', function (): void {
    $user = User::factory()->create([
        'email' => 'johndoe@gmail.com',
        'password' => 'password123456',
    ]);
    $user->profile()->save(Profile::factory()->make());
    $response = $this->postJson(route('login.store'), [
        'email' => 'johndoe@gmail.com',
        'password' => 'password123456',
    ]);
    $response->assertOk();
    $response->assertJsonStructure(['data' => LoginResource::JSON_STRUCTURE]);
    $response->assertJson([
        'data' => [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'avatar' => Storage::disk('s3')->url($user->profile?->avatar) ?? $user->profile?->avatar,
            ],
        ],
    ]);
    expect($response->json('data.access_token'))->toBeString();
});

it('validates login credentials', function (): void {
    $response = $this->postJson(route('login.store'), [
        'email' => 'johndo@gmail.com',
        'password' => 'password12345',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
});
it('validates login fields', function (): void {
    $response = $this->postJson(route('login.store'), []);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonValidationErrors(['email', 'password']);
});
it('returns error when login fails', function (): void {
    $user = User::factory()->create([
        'email' => 'johndoe@gmail.com',
        'password' => 'correctpassword123',
    ]);

    $response = $this->postJson(route('login.store'), [
        'email' => 'johndoe@gmail.com',
        'password' => 'wrongpassword123',
        'status' => Status::BLOCKED->value,
    ]);

    if (! $user->isActive()) {
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    $response->assertStatus(Response::HTTP_BAD_REQUEST);

});
it('returns unauthenticated when not logged in', function (): void {
    $response = $this->getJson(route('me.show'));

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    $response->assertJson([
        'message' => 'Unauthenticated.',
    ]);
});
it('returns user details', function (): void {
    $user = User::factory()->create();
    $user->profile()->save(Profile::factory()->make());
    $user->resume()->create([
        'name' => 'test-resume.pdf',
        'path' => 'resumes/test-resume.pdf',
        'extracted_text' => 'Test resume content',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(route('me.show'));

    $response->assertOk();
    $response->assertJsonStructure(['data' => ProfileResource::JSON_STRUCTURE]);
    $response->assertJson([
        'data' => [
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'avatar' => Storage::disk('s3')->url($user->profile?->avatar) ?? $user->profile?->avatar,
                'status' => $user->status->label(),
                'resume_name' => $user->resume?->name,
                'resume' => Storage::disk('s3')->url($user->resume?->path) ?? $user->resume?->path,
            ],
        ],
    ]);
});
it('can log out', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['*']);

    $response = $this->deleteJson(route('logout.destroy'));
    $response->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});
it('require authentication to log out', function (): void {

    $response = $this->deleteJson(route('logout.destroy'));

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);

});
