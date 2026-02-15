<?php

use App\Http\Controllers\Api\V1\BlogController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\GitHubController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ShareController;
use App\Http\Middleware\ValidatePreviewToken;
use Illuminate\Support\Facades\Route;

Route::get('/search', SearchController::class)->name('v1.search');

Route::post('/chat', ChatController::class)
    ->middleware('throttle:chat')
    ->name('v1.chat');

Route::get('/github/activity', [GitHubController::class, 'activity'])
    ->name('v1.github.activity');

Route::prefix('blogs')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('v1.blogs.index');
    Route::get('/featured', [BlogController::class, 'featured'])->name('v1.blogs.featured');
    Route::get('/preview/{slug}', [BlogController::class, 'preview'])->name('v1.blogs.preview')->middleware(ValidatePreviewToken::class);
    Route::get('/{slug}', [BlogController::class, 'show'])->name('v1.blogs.show');
});

Route::prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('v1.projects.index');
    Route::get('/featured', [ProjectController::class, 'featured'])->name('v1.projects.featured');
    Route::get('/preview/{slug}', [ProjectController::class, 'preview'])->name('v1.projects.preview')->middleware(ValidatePreviewToken::class);
    Route::get('/{slug}', [ProjectController::class, 'show'])->name('v1.projects.show');
});

Route::prefix('shares')->group(function () {
    Route::get('/', [ShareController::class, 'index'])->name('v1.shares.index');
    Route::get('/featured', [ShareController::class, 'featured'])->name('v1.shares.featured');
    Route::get('/{slug}', [ShareController::class, 'show'])->name('v1.shares.show');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/shares', [ShareController::class, 'store'])->name('v1.shares.store');
});
