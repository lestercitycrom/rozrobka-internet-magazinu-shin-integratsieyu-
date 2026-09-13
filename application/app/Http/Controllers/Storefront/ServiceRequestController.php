<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreServiceRequest;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use App\Modules\Content\ReadModel\ServiceMediaCatalog;
use App\Modules\ServiceBooking\BookingCalendar;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\SeasonalQuoteCalculator;
use App\Modules\ServiceBooking\ServiceRequestCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function create(string $slug, PublishedServicePageReadService $services, SeasonalQuoteCalculator $quotes, BookingCalendar $calendar, ServiceMediaCatalog $media): View
    {
        $service = $services->findBySlug($slug, $this->locale());
        abort_if($service === null, 404);

        $requestedFulfilment = old('fulfilment', request()->query('fulfilment', 'workshop'));
        $requestedFulfilment = in_array($requestedFulfilment, ['workshop', 'mobile'], true) ? $requestedFulfilment : 'workshop';

        return view('storefront.requests.create', [
            'service' => $service,
            'seasonalPrices' => $quotes->publicMatrix(),
            'scheduleConfigured' => $calendar->hasConfiguredSchedule($requestedFulfilment),
            'submissionToken' => old('submission_token', (string) Str::uuid()),
            'serviceMedia' => $media->for($service),
        ]);
    }

    public function store(StoreServiceRequest $request, string $slug, PublishedServicePageReadService $services, ServiceRequestCreator $creator): RedirectResponse
    {
        abort_if($services->findBySlug($slug, $this->locale()) === null, 404);
        $scope = $request->session()->get('service_request_scope');
        if (! is_string($scope) || $scope === '') {
            $scope = (string) Str::uuid();
            $request->session()->put('service_request_scope', $scope);
        }
        $item = $creator->createPublic(
            $request->validated(),
            $slug,
            hash('sha256', $scope),
            app()->getLocale(),
        );

        return redirect()->route($this->routePrefix().'requests.thanks', $item->reference);
    }

    public function thanks(string $reference): View
    {
        $request = ServiceRequest::query()->where('reference', $reference)->first();
        abort_if($request === null, 404);

        return view('storefront.requests.thanks', ['reference' => $request->reference]);
    }

    private function locale(): ServicePageLocale
    {
        return app()->getLocale() === 'ru' ? ServicePageLocale::Russian : ServicePageLocale::NorwegianBokmal;
    }

    private function routePrefix(): string
    {
        return app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    }
}
