<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLocaleController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\Identity\AdminLoginController;
use App\Http\Controllers\Admin\Identity\AdminSessionController;
use App\Http\Controllers\Admin\ImportRunController;
use App\Http\Controllers\Admin\MediaAssetController;
use App\Http\Controllers\Admin\MediaHeroAssignmentController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PricingRuleController;
use App\Http\Controllers\Admin\ServicePageController;
use App\Http\Controllers\Admin\ServiceRequestController;
use App\Http\Controllers\Admin\SystemHealthController;
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
        Route::get('/health', SystemHealthController::class)->name('health');
        Route::get('/service-requests', [ServiceRequestController::class, 'index'])->name('service-requests.index');
        Route::get('/service-requests/{request}', [ServiceRequestController::class, 'show'])->name('service-requests.show');
        Route::put('/service-requests/{request}', [ServiceRequestController::class, 'update'])->name('service-requests.update');
        Route::resource('content-pages', ContentPageController::class)->except('show');
        Route::resource('pricing-rules', PricingRuleController::class)->except('show', 'destroy');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/transition', [OrderController::class, 'transition'])->name('orders.transition');
        Route::get('/media', [MediaAssetController::class, 'index'])->name('media.index');
        Route::post('/media', [MediaAssetController::class, 'store'])->name('media.store');
        Route::put('/media/{mediaAsset}', [MediaAssetController::class, 'update'])->name('media.update');
        Route::delete('/media/{mediaAsset}', [MediaAssetController::class, 'destroy'])->name('media.destroy');
        Route::post('/media/hero/{type}/{id}', [MediaHeroAssignmentController::class, 'store'])->whereIn('type', ['service', 'content', 'product'])->name('media.hero.store');
        Route::delete('/media/hero/{type}/{id}', [MediaHeroAssignmentController::class, 'destroy'])->whereIn('type', ['service', 'content', 'product'])->name('media.hero.destroy');
        Route::post('/locale', [AdminLocaleController::class, 'update'])->name('locale.update');
        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});
