<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLocaleController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\Identity\AdminLoginController;
use App\Http\Controllers\Admin\Identity\AdminSessionController;
use App\Http\Controllers\Admin\ImportRunController;
use App\Http\Controllers\Admin\ServicePageController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('public.locale')->group(function (): void {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware(['admin.active', 'admin.locale'])->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('/catalog/{product}', [CatalogController::class, 'show'])->name('catalog.show');
        Route::get('/imports', [ImportRunController::class, 'index'])->name('imports.index');
        Route::get('/imports/{runId}', [ImportRunController::class, 'show'])->name('imports.show');
        Route::resource('services', ServicePageController::class)->except('show', 'destroy');
        Route::post('/locale', [AdminLocaleController::class, 'update'])->name('locale.update');
        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});
