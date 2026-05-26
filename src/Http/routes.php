<?php

use Illuminate\Support\Facades\Route;
use Seiger\sMultisite\Controllers\SsoController;
use Seiger\sMultisite\Controllers\sMultisiteController;

foreach (['_ms-run', '_ms-run-logout', '_ms-sso', '_ms-sso-logout'] as $endpoint) {
    Route::any($endpoint, [SsoController::class, 'handle'])->defaults('ssoEndpoint', $endpoint);
    Route::any($endpoint . '.{suffix}', [SsoController::class, 'handle'])
        ->where('suffix', '.*')
        ->defaults('ssoEndpoint', $endpoint);
}

Route::middleware('mgr')->prefix('smultisite/')->name('sMultisite.')->group(function () {
    Route::get('configure', [sMultisiteController::class, 'configure'])->name('configure');
    Route::post('configure', [sMultisiteController::class, 'updateConfigure'])->name('uconfigure');
    Route::post('adomain', [sMultisiteController::class, 'addNewDomains'])->name('adomain');
});
