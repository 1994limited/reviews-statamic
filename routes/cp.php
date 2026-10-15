<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\Reviews\Http\Controllers\CP\ReviewsController;

Route::prefix('reviews')->name('reviews.')->group(function () {
    Route::get('/', [ReviewsController::class, 'index'])->name('index');
    Route::post('bulk', [ReviewsController::class, 'bulk'])->name('bulk');
    Route::get('{review}', [ReviewsController::class, 'show'])->name('show');
    Route::post('{review}/status', [ReviewsController::class, 'status'])->name('status');
    Route::post('{review}/reply', [ReviewsController::class, 'reply'])->name('reply');
    Route::post('{review}/note', [ReviewsController::class, 'note'])->name('note');
    Route::delete('{review}', [ReviewsController::class, 'destroy'])->name('destroy');
    Route::get('{review}/photos/{index}', [ReviewsController::class, 'photo'])->name('photo')->whereNumber('index');
});
