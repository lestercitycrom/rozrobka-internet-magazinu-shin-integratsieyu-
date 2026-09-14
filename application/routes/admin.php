<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLocaleController;
use App\Http\Controllers\Admin\BookingCalendarController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientLookupController;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\HomepageBusinessPartnerController;
use App\Http\Controllers\Admin\HomepagePromotionController;
use App\Http\Controllers\Admin\HomepageRatingSummaryController;
use App\Http\Controllers\Admin\HomepageReviewController;
use App\Http\Controllers\Admin\HomepageServiceCardController;
use App\Http\Controllers\Admin\Identity\AdminLoginController;
use App\Http\Controllers\Admin\Identity\AdminSessionController;
use App\Http\Controllers\Admin\ImportRunController;
use App\Http\Controllers\Admin\MediaAssetController;
use App\Http\Controllers\Admin\MediaHeroAssignmentController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderPaymentController;
use App\Http\Controllers\Admin\PricingRuleController;
use App\Http\Controllers\Admin\PublicServicePriceController;
use App\Http\Controllers\Admin\ServiceAddonController;
use App\Http\Controllers\Admin\ServicePageController;
use App\Http\Controllers\Admin\ServiceRequestController;
use App\Http\Controllers\Admin\ServiceRequestDayController;
use App\Http\Controllers\Admin\StorefrontSettingsController;
use App\Http\Controllers\Admin\SystemHealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('public.locale')->group(function (): void {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware(['admin.active', 'admin.locale'])->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::middleware('admin.roles:owner,operations')->group(function (): void {
            Route::get('/clients/lookup', ClientLookupController::class)->name('clients.lookup');
            Route::resource('clients', ClientController::class)->except('destroy');
            Route::get('/service-requests', [ServiceRequestController::class, 'index'])->name('service-requests.index');
            Route::get('/service-requests/create', [ServiceRequestController::class, 'create'])->name('service-requests.create');
            Route::post('/service-requests', [ServiceRequestController::class, 'store'])->name('service-requests.store');
            Route::get('/service-requests/day', ServiceRequestDayController::class)->name('service-requests.day');
            Route::get('/service-requests/{request}/edit', [ServiceRequestController::class, 'edit'])->name('service-requests.edit');
            Route::get('/service-requests/{request}', [ServiceRequestController::class, 'show'])->name('service-requests.show');
            Route::match(['put', 'patch'], '/service-requests/{request}', [ServiceRequestController::class, 'update'])->name('service-requests.update');
            Route::get('/booking-calendar', [BookingCalendarController::class, 'index'])->name('booking-calendar.index');
        });

        Route::middleware('admin.roles:owner,editor')->group(function (): void {
            Route::resource('services', ServicePageController::class)->except('show', 'destroy');
            Route::resource('content-pages', ContentPageController::class)->except('show');
            Route::resource('homepage-promotions', HomepagePromotionController::class)->except('show');
            Route::prefix('homepage-service-collections/{collection}')
                ->whereIn('collection', ['additional', 'wheels'])
                ->name('homepage-service-collections.')
                ->group(function (): void {
                    Route::get('/', [HomepageServiceCardController::class, 'index'])->name('index');
                    Route::get('/create', [HomepageServiceCardController::class, 'create'])->name('create');
                    Route::post('/', [HomepageServiceCardController::class, 'store'])->name('store');
                    Route::get('/{homepageServiceCard}/edit', [HomepageServiceCardController::class, 'edit'])->name('edit');
                    Route::put('/{homepageServiceCard}', [HomepageServiceCardController::class, 'update'])->name('update');
                    Route::delete('/{homepageServiceCard}', [HomepageServiceCardController::class, 'destroy'])->name('destroy');
                });
            Route::resource('homepage-reviews', HomepageReviewController::class)->except('show');
            Route::resource('homepage-rating-summaries', HomepageRatingSummaryController::class)->except('show');
            Route::resource('homepage-business-partners', HomepageBusinessPartnerController::class)->except('show');
            Route::get('/media', [MediaAssetController::class, 'index'])->name('media.index');
            Route::post('/media', [MediaAssetController::class, 'store'])->name('media.store');
            Route::put('/media/{mediaAsset}', [MediaAssetController::class, 'update'])->name('media.update');
            Route::delete('/media/{mediaAsset}', [MediaAssetController::class, 'destroy'])->name('media.destroy');
            Route::post('/media/hero/{type}/{id}', [MediaHeroAssignmentController::class, 'store'])->whereIn('type', ['service', 'content', 'product'])->name('media.hero.store');
            Route::delete('/media/hero/{type}/{id}', [MediaHeroAssignmentController::class, 'destroy'])->whereIn('type', ['service', 'content', 'product'])->name('media.hero.destroy');
        });

        Route::middleware('admin.roles:owner')->group(function (): void {
            if (app()->environment(['local', 'testing']) && config('app.e2e_admin_visual_states')) {
                Route::get('/_e2e/dashboard/{state}', function (string $state) {
                    $states = [
                        'zero' => ['serviceRequestCounts' => ['attention' => 0, 'total' => 0], 'orderCounts' => ['attention' => 0, 'total' => 0]],
                        'low' => ['serviceRequestCounts' => ['attention' => 1, 'total' => 3], 'orderCounts' => ['attention' => 1, 'total' => 2]],
                        'large' => ['serviceRequestCounts' => ['attention' => 987, 'total' => 1234], 'orderCounts' => ['attention' => 876, 'total' => 4321]],
                    ];

                    abort_unless(array_key_exists($state, $states), 404);

                    return view('admin.dashboard', $states[$state] + ['e2eDashboardState' => $state]);
                })->whereIn('state', ['zero', 'low', 'large'])->name('e2e.dashboard-state');
            }

            Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
            Route::get('/catalog/publication-preview', [CatalogController::class, 'publicationPreview'])->name('catalog.publication.preview');
            Route::put('/catalog/publication', [CatalogController::class, 'publishEligible'])->name('catalog.publication.publish');
            Route::get('/catalog/{product}', [CatalogController::class, 'show'])->name('catalog.show');
            Route::put('/catalog/{product}/publication', [CatalogController::class, 'updatePublication'])->name('catalog.publication.update');
            Route::get('/imports', [ImportRunController::class, 'index'])->name('imports.index');
            Route::get('/imports/{runId}', [ImportRunController::class, 'show'])->name('imports.show');
            Route::get('/service-addons', [ServiceAddonController::class, 'index'])->name('service-addons.index');
            Route::get('/service-addons/{serviceAddon}/edit', [ServiceAddonController::class, 'edit'])->name('service-addons.edit');
            Route::put('/service-addons/{serviceAddon}', [ServiceAddonController::class, 'update'])->name('service-addons.update');
            Route::get('/health', SystemHealthController::class)->name('health');
            Route::get('/booking-calendar/intervals/create', [BookingCalendarController::class, 'createInterval'])->name('booking-calendar.intervals.create');
            Route::post('/booking-calendar/intervals', [BookingCalendarController::class, 'storeInterval'])->name('booking-calendar.intervals.store');
            Route::get('/booking-calendar/intervals/{interval}/edit', [BookingCalendarController::class, 'editInterval'])->name('booking-calendar.intervals.edit');
            Route::put('/booking-calendar/intervals/{interval}', [BookingCalendarController::class, 'updateInterval'])->name('booking-calendar.intervals.update');
            Route::delete('/booking-calendar/intervals/{interval}', [BookingCalendarController::class, 'destroyInterval'])->name('booking-calendar.intervals.destroy');
            Route::get('/booking-calendar/blackouts/create', [BookingCalendarController::class, 'createBlackout'])->name('booking-calendar.blackouts.create');
            Route::post('/booking-calendar/blackouts', [BookingCalendarController::class, 'storeBlackout'])->name('booking-calendar.blackouts.store');
            Route::get('/booking-calendar/blackouts/{blackout}/edit', [BookingCalendarController::class, 'editBlackout'])->name('booking-calendar.blackouts.edit');
            Route::put('/booking-calendar/blackouts/{blackout}', [BookingCalendarController::class, 'updateBlackout'])->name('booking-calendar.blackouts.update');
            Route::delete('/booking-calendar/blackouts/{blackout}', [BookingCalendarController::class, 'destroyBlackout'])->name('booking-calendar.blackouts.destroy');
            Route::get('/storefront-settings', [StorefrontSettingsController::class, 'edit'])->name('storefront-settings.edit');
            Route::put('/storefront-settings', [StorefrontSettingsController::class, 'update'])->name('storefront-settings.update');
            Route::resource('pricing-rules', PricingRuleController::class)->except('show', 'destroy');
            Route::resource('public-service-prices', PublicServicePriceController::class)->except('show');
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{order}/transition', [OrderController::class, 'transition'])->name('orders.transition');
            Route::post('/orders/{order}/payment/capture', [OrderPaymentController::class, 'capture'])->name('orders.payment.capture');
            Route::post('/orders/{order}/payment/refund', [OrderPaymentController::class, 'refund'])->name('orders.payment.refund');
            Route::post('/orders/{order}/payment/reconcile', [OrderPaymentController::class, 'reconcile'])->name('orders.payment.reconcile');
            Route::post('/orders/{order}/payment/void', [OrderPaymentController::class, 'void'])->name('orders.payment.void');
        });

        Route::post('/locale', [AdminLocaleController::class, 'update'])->name('locale.update');
        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});
