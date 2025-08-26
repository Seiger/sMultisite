<?php

use Illuminate\Support\Facades\Route;
use Seiger\sMultisite\Controllers\sMultisiteController;

Route::middleware('mgr')->prefix('smultisite/')->name('sMultisite.')->group(function () {
    Route::get('configure', [sMultisiteController::class, 'configure'])->name('configure');
    Route::post('configure', [sMultisiteController::class, 'updateConfigure'])->name('uconfigure');
    Route::post('adomain', [sMultisiteController::class, 'addNewDomains'])->name('adomain');
});
