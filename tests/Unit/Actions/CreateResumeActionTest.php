<?php

declare(strict_types=1);

use App\Actions\CreateResumeAction;
use App\Jobs\ExtractResumeTextJob;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake();
});

describe('CreateResumeAction', function (): void {
    it('creates a resume from uploaded file', function (): void {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('resume.pdf', 1024);

        $action = resolve(CreateResumeAction::class);

        $resume = $action->handle(['path' => $file], $user);

        expect($resume)->toBeInstanceOf(Resume::class);
        expect($resume->user_id)->toBe($user->id);
        expect($resume->name)->toBe('resume.pdf');
        expect($resume->path)->toContain('resumes/'.$user->id);
    });

    it('creates resume with string path', function (): void {
        $user = User::factory()->create();
        $filePath = 'resumes/test.pdf';

        $action = resolve(CreateResumeAction::class);

        $resume = $action->handle(['path' => $filePath], $user);

        expect($resume)->toBeInstanceOf(Resume::class);
        expect($resume->path)->toBe($filePath);
        expect($resume->name)->toBe('test.pdf');
    });

    it('dispatches ExtractResumeTextJob after creating resume', function (): void {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('resume.pdf', 1024);

        $action = resolve(CreateResumeAction::class);

        $action->handle(['path' => $file], $user);

        Queue::assertPushed(ExtractResumeTextJob::class);
    });

    it('replaces old resume when user already has one', function (): void {
        $user = User::factory()->create();
        $oldFile = UploadedFile::fake()->create('old_resume.pdf', 512);
        $oldPath = $oldFile->storeAs('resumes/'.$user->id, 'old_resume.pdf', 'public');

        Resume::factory()->for($user)->create([
            'path' => $oldPath,
            'name' => 'old_resume.pdf',
        ]);

        Storage::disk('public')->assertExists($oldPath);

        $newFile = UploadedFile::fake()->create('new_resume.pdf', 1024);
        $action = resolve(CreateResumeAction::class);
        $resume = $action->handle(['path' => $newFile], $user);

        expect($resume->name)->toBe('new_resume.pdf');
        expect($resume->path)->not->toBe($oldPath);

        Storage::disk('public')->assertMissing($oldPath);
    });
});
