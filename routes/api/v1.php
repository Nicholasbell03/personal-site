<?php

use App\Http\Controllers\Api\V1\BlogController;
use Illuminate\Support\Facades\Route;

Route::prefix('blogs')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('v1.blogs.index');
    Route::get('/featured', [BlogController::class, 'featured'])->name('v1.blogs.featured');
    Route::get('/{slug}', [BlogController::class, 'show'])->name('v1.blogs.show');
});
