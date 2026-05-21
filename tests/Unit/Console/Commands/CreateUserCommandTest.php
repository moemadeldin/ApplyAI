<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates user with valid input', function (): void {
    $this->artisan('users:create')
        ->expectsQuestion('Email of the new user', 'testing@app.com')
        ->expectsQuestion('Password of the new user', 'Password1!')
        ->expectsOutput('User testing@app.com created successfully')
        ->assertExitCode(0);

    $this->assertDatabaseHas('users', ['email' => 'testing@app.com']);
});

test('shows validation errors for empty input', function (): void {
    $this->artisan('users:create')
        ->expectsQuestion('Email of the new user', '')
        ->expectsQuestion('Password of the new user', '')
        ->assertExitCode(0);
});

test('shows validation errors for invalid email', function (): void {
    $this->artisan('users:create')
        ->expectsQuestion('Email of the new user', 'not-an-email')
        ->expectsQuestion('Password of the new user', 'Password1!')
        ->assertExitCode(0);
});

test('shows validation errors for weak password', function (): void {
    $this->artisan('users:create')
        ->expectsQuestion('Email of the new user', 'test@app.com')
        ->expectsQuestion('Password of the new user', 'weak')
        ->assertExitCode(0);
});
