<?php

use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\ServiceController;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use Illuminate\Support\Facades\Route;

Route::middleware('public.locale')->group(function (): void {
    Route::get('/', function (PublishedServicePageReadService $services) {
        return view('welcome', ['services' => $services->list(ServicePageLocale::NorwegianBokmal)]);
    })->name('storefront.home');
    Route::get('/dekk', [CatalogController::class, 'tires'])->name('storefront.tires.index');
    Route::get('/felger', [CatalogController::class, 'wheels'])->name('storefront.wheels.index');
    Route::get('/produkter/{sku}', [CatalogController::class, 'show'])->name('storefront.products.show');
    Route::get('/tjenester', [ServiceController::class, 'index'])->name('storefront.services.index');
    Route::get('/tjenester/{slug}', [ServiceController::class, 'show'])->name('storefront.services.show');
});

Route::prefix('ru')->as('storefront.preview.')->middleware('public.preview.ru')->group(function (): void {
    Route::get('/', fn (PublishedServicePageReadService $services) => view('welcome', ['services' => $services->list(ServicePageLocale::Russian)]))->name('home');
    Route::get('/dekk', [CatalogController::class, 'tires'])->name('tires.index');
    Route::get('/felger', [CatalogController::class, 'wheels'])->name('wheels.index');
    Route::get('/produkter/{sku}', [CatalogController::class, 'show'])->name('products.show');
    Route::get('/tjenester', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/tjenester/{slug}', [ServiceController::class, 'show'])->name('services.show');
});
