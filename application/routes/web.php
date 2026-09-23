<?php

use App\Http\Controllers\Storefront\BookingAvailabilityController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\ContentPageController;
use App\Http\Controllers\Storefront\DinteroPaymentController;
use App\Http\Controllers\Storefront\PricingPageController;
use App\Http\Controllers\Storefront\SeoServicePageController;
use App\Http\Controllers\Storefront\ServiceController;
use App\Http\Controllers\Storefront\ServiceRequestController;
use App\Http\Controllers\Storefront\SiteSearchController;
use App\Http\Controllers\Storefront\VehicleFitmentController;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\ReadModel\CatalogReadService;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use App\Modules\Content\ReadModel\StorefrontSettingsReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', function () {
    if (! app()->environment('production')) {
        return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
    }

    return response("User-agent: *\nDisallow: /admin\nDisallow: /ru\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain']);
});

Route::get('/betaling/dintero/callback', [DinteroPaymentController::class, 'callback'])
    ->middleware('throttle:60,1')
    ->name('payments.dintero.callback');

Route::get('/sitemap.xml', function (CatalogReadService $catalog) {
    $urls = [url('/'), url('/dekk-og-felger'), url('/dekk'), url('/felger'), url('/tjenester'), url('/priser')];
    $servicePages = ServicePage::query()->where('publication_status', 'published')->orderBy('sort_order')->get(['slug', 'canonical_path']);
    $urls = [...$urls, ...$servicePages->map(fn (ServicePage $page) => url('/'.($page->canonical_path ?: 'tjenester/'.$page->slug)))->all()];
    $contentPages = ContentPage::query()->where('publication_status', 'published')->whereIn('slug', ContentPage::ROUTABLE_SLUGS)->orderBy('sort_order')->pluck('slug');
    $urls = [...$urls, ...$contentPages->map(fn (string $slug) => url('/'.$slug))->all()];
    $urls = [...$urls, ...collect($catalog->publicSkuCodes())->map(fn ($sku) => url('/produkter/'.$sku))->all()];
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.collect($urls)->unique()->map(fn ($location) => '<url><loc>'.e($location).'</loc></url>')->implode('').'</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml']);
});

Route::middleware('public.locale')->group(function (): void {
    Route::get('/', function (PublishedServicePageReadService $services, StorefrontSettingsReadService $settings) {
        return view('welcome', ['services' => $services->list(ServicePageLocale::NorwegianBokmal), 'settings' => $settings->current()]);
    })->name('storefront.home');
    Route::get('/dekk', [CatalogController::class, 'tires'])->name('storefront.tires.index');
    Route::get('/dekk/suggestions', fn (Request $request, CatalogReadService $catalog) => app(CatalogController::class)->suggestions($request, $catalog, ProductType::Tire))->middleware('throttle:60,1')->name('storefront.tires.suggestions');
    Route::get('/biloppslag', [VehicleFitmentController::class, 'create'])->name('storefront.fitment.create');
    Route::post('/biloppslag', [VehicleFitmentController::class, 'store'])->name('storefront.fitment.store');
    Route::post('/biloppslag/bekreft', [VehicleFitmentController::class, 'confirm'])->name('storefront.fitment.confirm');
    Route::get('/dekk-og-felger', [VehicleFitmentController::class, 'create'])->name('storefront.store.index');
    Route::post('/dekk-og-felger', [VehicleFitmentController::class, 'store'])->name('storefront.store.lookup');
    Route::redirect('/shop', '/dekk-og-felger', 301)->name('storefront.store.legacy');
    Route::get('/felger', [CatalogController::class, 'wheels'])->name('storefront.wheels.index');
    Route::get('/felger/suggestions', fn (Request $request, CatalogReadService $catalog) => app(CatalogController::class)->suggestions($request, $catalog, ProductType::Wheel))->middleware('throttle:60,1')->name('storefront.wheels.suggestions');
    Route::get('/produkter/{sku}', [CatalogController::class, 'show'])->name('storefront.products.show');
    Route::get('/priser', PricingPageController::class)->name('storefront.pricing.index');
    Route::get('/sok', SiteSearchController::class)->name('storefront.site-search.index');
    Route::get('/tjenester', [ServiceController::class, 'index'])->name('storefront.services.index');
    Route::get('/tjenester/{slug}', [ServiceController::class, 'show'])->name('storefront.services.show');
    Route::get('/{slug}', [ContentPageController::class, 'show'])->whereIn('slug', ContentPage::ROUTABLE_SLUGS)->name('storefront.content.show');
    Route::get('/tjenester/{slug}/foresporsel', [ServiceRequestController::class, 'create'])->name('storefront.requests.create');
    Route::get('/booking/availability', BookingAvailabilityController::class)->middleware('throttle:60,1')->name('storefront.booking.availability');
    Route::post('/tjenester/{slug}/foresporsel', [ServiceRequestController::class, 'store'])->middleware('throttle:10,1')->name('storefront.requests.store');
    Route::get('/foresporsel/{reference}/takk', [ServiceRequestController::class, 'thanks'])->name('storefront.requests.thanks');
    Route::get('/handlekurv', [CartController::class, 'index'])->name('storefront.cart.index');
    Route::post('/handlekurv', [CartController::class, 'add'])->name('storefront.cart.add');
    Route::patch('/handlekurv', [CartController::class, 'update'])->name('storefront.cart.update');
    Route::delete('/handlekurv', [CartController::class, 'remove'])->name('storefront.cart.remove');
    Route::post('/handlekurv/tjenester', [CartController::class, 'addServiceAddon'])->name('storefront.cart.services.add');
    Route::delete('/handlekurv/tjenester', [CartController::class, 'removeServiceAddon'])->name('storefront.cart.services.remove');
    Route::get('/kasse', [CheckoutController::class, 'create'])->name('storefront.checkout.create');
    Route::post('/kasse', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('storefront.checkout.store');
    Route::get('/bestilling/{reference}', [CheckoutController::class, 'thanks'])->name('storefront.orders.thanks');
    Route::get('/betaling/dintero/retur/{reference}', [DinteroPaymentController::class, 'return'])->name('storefront.payments.dintero.return');
    // Keep this dynamic CMS route last so fixed storefront paths always win.
    Route::get('/{canonicalPath}', SeoServicePageController::class)
        ->where('canonicalPath', '(?!admin$)(?!ru$)[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('storefront.seo-services.show');
});

Route::prefix('ru')->as('storefront.preview.')->middleware('public.preview.ru')->group(function (): void {
    Route::get('/', fn (PublishedServicePageReadService $services, StorefrontSettingsReadService $settings) => view('welcome', ['services' => $services->list(ServicePageLocale::Russian), 'settings' => $settings->current()]))->name('home');
    Route::get('/dekk', [CatalogController::class, 'tires'])->name('tires.index');
    Route::get('/dekk/suggestions', fn (Request $request, CatalogReadService $catalog) => app(CatalogController::class)->suggestions($request, $catalog, ProductType::Tire))->middleware('throttle:60,1')->name('tires.suggestions');
    Route::get('/biloppslag', [VehicleFitmentController::class, 'create'])->name('fitment.create');
    Route::post('/biloppslag', [VehicleFitmentController::class, 'store'])->name('fitment.store');
    Route::post('/biloppslag/bekreft', [VehicleFitmentController::class, 'confirm'])->name('fitment.confirm');
    Route::get('/dekk-og-felger', [VehicleFitmentController::class, 'create'])->name('store.index');
    Route::post('/dekk-og-felger', [VehicleFitmentController::class, 'store'])->name('store.lookup');
    Route::redirect('/shop', '/ru/dekk-og-felger', 301)->name('store.legacy');
    Route::get('/felger', [CatalogController::class, 'wheels'])->name('wheels.index');
    Route::get('/felger/suggestions', fn (Request $request, CatalogReadService $catalog) => app(CatalogController::class)->suggestions($request, $catalog, ProductType::Wheel))->middleware('throttle:60,1')->name('wheels.suggestions');
    Route::get('/produkter/{sku}', [CatalogController::class, 'show'])->name('products.show');
    Route::get('/priser', PricingPageController::class)->name('pricing.index');
    Route::get('/sok', SiteSearchController::class)->name('site-search.index');
    Route::get('/tjenester', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/tjenester/{slug}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('/{slug}', [ContentPageController::class, 'show'])->whereIn('slug', ContentPage::ROUTABLE_SLUGS)->name('content.show');
    Route::get('/tjenester/{slug}/foresporsel', [ServiceRequestController::class, 'create'])->name('requests.create');
    Route::get('/booking/availability', BookingAvailabilityController::class)->middleware('throttle:60,1')->name('booking.availability');
    Route::post('/tjenester/{slug}/foresporsel', [ServiceRequestController::class, 'store'])->middleware('throttle:10,1')->name('requests.store');
    Route::get('/foresporsel/{reference}/takk', [ServiceRequestController::class, 'thanks'])->name('requests.thanks');
    Route::get('/handlekurv', [CartController::class, 'index'])->name('cart.index');
    Route::post('/handlekurv', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/handlekurv', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/handlekurv', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/handlekurv/tjenester', [CartController::class, 'addServiceAddon'])->name('cart.services.add');
    Route::delete('/handlekurv/tjenester', [CartController::class, 'removeServiceAddon'])->name('cart.services.remove');
    Route::get('/kasse', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/kasse', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/bestilling/{reference}', [CheckoutController::class, 'thanks'])->name('orders.thanks');
    Route::get('/betaling/dintero/retur/{reference}', [DinteroPaymentController::class, 'return'])->name('payments.dintero.return');
    Route::get('/{canonicalPath}', SeoServicePageController::class)
        ->where('canonicalPath', '(?!admin$)[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('seo-services.show');
});
