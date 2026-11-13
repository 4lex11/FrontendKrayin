<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Webkul\Churn\Http\Controllers\ChurnController;
use Webkul\Churn\Http\Middleware\ChurnCsrfToken;

Route::middleware(['web', 'admin_locale', 'user'])
    ->prefix(trim(config('app.admin_path'), '/').'/churn')
    ->group(function () {
        Route::get('/customers/{customer}', [ChurnController::class, 'show'])->whereNumber('customer')->name('admin.churn.show');
        Route::post('/updates', [ChurnController::class, 'update'])->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])->middleware([ChurnCsrfToken::class, 'throttle:10,1'])->name('admin.churn.update');
        Route::post('/customers/{customer}/reviews', [ChurnController::class, 'review'])->whereNumber('customer')->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])->middleware([ChurnCsrfToken::class, 'throttle:20,1'])->name('admin.churn.review');
        Route::get('/', [ChurnController::class, 'index'])->name('admin.churn.index');
    });
