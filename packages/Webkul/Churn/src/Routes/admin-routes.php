<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Webkul\Churn\Http\Controllers\ChurnController;
use Webkul\Churn\Http\Controllers\DatasetDashboardController;
use Webkul\Churn\Http\Middleware\ChurnCsrfToken;

Route::middleware(['web', 'admin_locale', 'user'])
    ->prefix(trim(config('app.admin_path'), '/').'/churn')
    ->group(function () {
        Route::get('/dataset', [DatasetDashboardController::class, 'index'])->name('admin.churn.dataset.index');
        Route::get('/dataset/explore', [DatasetDashboardController::class, 'explore'])->name('admin.churn.dataset.explore');
        Route::get('/dataset/customers/{customer}', [DatasetDashboardController::class, 'customer'])
            ->where('customer', '[A-Za-z0-9_.:-]{1,100}')->name('admin.churn.dataset.customer');
        Route::get('/dataset/orders/{order}', [DatasetDashboardController::class, 'order'])
            ->where('order', '[A-Za-z0-9_.:-]{1,100}')->name('admin.churn.dataset.order');
        Route::get('/customers/{customer}', [ChurnController::class, 'show'])->whereNumber('customer')->name('admin.churn.show');
        Route::post('/updates', [ChurnController::class, 'update'])->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])->middleware([ChurnCsrfToken::class, 'throttle:10,1'])->name('admin.churn.update');
        Route::post('/customers/{customer}/reviews', [ChurnController::class, 'review'])->whereNumber('customer')->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])->middleware([ChurnCsrfToken::class, 'throttle:20,1'])->name('admin.churn.review');
        Route::get('/', [ChurnController::class, 'index'])->name('admin.churn.index');
    });
