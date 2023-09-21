<?php

use Illuminate\Support\Facades\Route;
use Seiger\sMultisite\Controllers\sMultisiteController;

Route::middleware('mgr')->prefix('smultisite/')->name('sMultisite.')->group(function () {
    Route::get('/', [sMultisiteController::class, 'index'])->name('index');
    Route::post('/', [sMultisiteController::class, 'update'])->name('update');
});
