<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\Reviews\Http\Controllers\CP\ImportController;
use NineteenNinetyFour\Reviews\Http\Controllers\CP\ReviewsController;
use NineteenNinetyFour\Reviews\Http\Controllers\CP\SourcesController;

Route::prefix('reviews')->name('reviews.')->group(function () {
    Route::get('/', [ReviewsController::class, 'index'])->name('index');
    Route::get('import', [ImportController::class, 'index'])->name('import');
    Route::post('import/preview', [ImportController::class, 'preview'])->name('import.preview');
    Route::post('import/run', [ImportController::class, 'run'])->name('import.run');
    Route::get('sync', [SourcesController::class, 'index'])->name('sources');
    Route::get('sync/new', [SourcesController::class, 'create'])->name('sources.create');
    Route::post('sync', [SourcesController::class, 'store'])->name('sources.store');
    Route::get('sync/{source}', [SourcesController::class, 'edit'])->name('sources.edit');
    Route::patch('sync/{source}', [SourcesController::class, 'update'])->name('sources.update');
    Route::delete('sync/{source}', [SourcesController::class, 'destroy'])->name('sources.destroy');
    Route::post('sync/{source}/run', [SourcesController::class, 'run'])->name('sources.run');
    // Reloading the page after a preview or an import starts again.
    Route::get('import/{step}', fn () => redirect()->to(cp_route('reviews.import')))->whereIn('step', ['preview', 'run']);
    Route::post('bulk', [ReviewsController::class, 'bulk'])->name('bulk');
    Route::get('{review}', [ReviewsController::class, 'show'])->name('show');
    Route::post('{review}/status', [ReviewsController::class, 'status'])->name('status');
    Route::post('{review}/reply', [ReviewsController::class, 'reply'])->name('reply');
    Route::post('{review}/note', [ReviewsController::class, 'note'])->name('note');
    Route::delete('{review}', [ReviewsController::class, 'destroy'])->name('destroy');
    Route::get('{review}/photos/{index}', [ReviewsController::class, 'photo'])->name('photo')->whereNumber('index');
});
