<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLocaleController;
use App\Http\Controllers\Admin\Identity\AdminLoginController;
use App\Http\Controllers\Admin\Identity\AdminSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('public.locale')->group(function (): void {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware(['admin.active', 'admin.locale'])->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::post('/locale', [AdminLocaleController::class, 'update'])->name('locale.update');
        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});
