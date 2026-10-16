<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\Reviews\Http\Controllers\ActionsController;

// At /!/reviews/...

// The default styles for the ready-made views ({{ reviews:styles }}).
Route::get('reviews.css', [ActionsController::class, 'styles'])->name('styles');

// Writing, changing and deleting a review. Limited per visitor.
Route::middleware('throttle:reviews-submit')->group(function () {
    Route::post('submit', [ActionsController::class, 'submit'])->name('submit');
    Route::post('delete', [ActionsController::class, 'destroy'])->name('delete');
});

Route::post('{review}/helpful', [ActionsController::class, 'helpful'])
    ->middleware('throttle:reviews-votes')
    ->where('review', '[a-z0-9]{1,64}')
    ->name('helpful');

// Links in review request emails (with Cargo). Signed, so they can't be
// made up for someone else's order or address.
Route::middleware('signed')->group(function () {
    Route::get('order/{order}/{product}', [ActionsController::class, 'fromOrder'])->name('order');
    Route::get('opt-out', [ActionsController::class, 'optOut'])->name('opt-out');
    Route::post('opt-out', [ActionsController::class, 'confirmOptOut'])->name('opt-out.confirm');
});
