<?php

use App\Http\Controllers\BlogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('blogs')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('blogs.index');
    Route::get('/featured', [BlogController::class, 'featured'])->name('blogs.featured');
    Route::get('/{slug}', [BlogController::class, 'show'])->name('blogs.show');
});

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
