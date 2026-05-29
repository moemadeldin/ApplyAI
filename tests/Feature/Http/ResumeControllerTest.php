<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

describe('ResumeController', function (): void {
    it('can get resume', function (): void {
        $user = User::factory()->create();
        $user->resume()->create([
            'name' => 'test.pdf',
            'path' => 'resumes/test.pdf',
            'extracted_text' => 'Test content',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('resumes.index'));

        $response->assertOk();
    });

    it('can upload resume', function (): void {
        Storage::fake('s3');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $resumeFile = UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf');

        $response = $this->post(route('resumes.store'), [
            'path' => $resumeFile,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $user->load('resume');

        expect($user->resume)->not->toBeNull();
    });

    it('requires authentication', function (): void {
        $response = $this->getJson(route('resumes.index'));

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    });
});
