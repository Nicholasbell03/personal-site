<?php

use App\Http\Controllers\Api\V1\BlogController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Middleware\ValidatePreviewToken;
use Illuminate\Support\Facades\Route;

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
