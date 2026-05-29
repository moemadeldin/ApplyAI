<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): Factory|View => view('welcome'));
Route::get('/test-image', fn (): string => file_exists(storage_path('app/public/profile_pictures/default_pfp.PNG'))
    ? 'FOUND'
    : 'NOT FOUND');
