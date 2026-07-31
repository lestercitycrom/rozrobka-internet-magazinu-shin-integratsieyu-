<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreServiceRequest;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function create(string $slug, PublishedServicePageReadService $services): View
    {
        $service = $services->findBySlug($slug, $this->locale());
        abort_if($service === null, 404);

        return view('storefront.requests.create', compact('service'));
    }

    public function store(StoreServiceRequest $request, string $slug, PublishedServicePageReadService $services): RedirectResponse
    {
        abort_if($services->findBySlug($slug, $this->locale()) === null, 404);
        $data = $request->validated();
        unset($data['consent'], $data['website']);

        $item = ServiceRequest::create([
            ...$data,
            'reference' => $this->reference(),
            'locale' => app()->getLocale(),
            'service_slug' => $slug,
            'dekkhotell' => $request->boolean('dekkhotell'),
            'email' => mb_strtolower($data['email']),
            'status' => ServiceRequestStatus::New,
            'consent_at' => now(),
        ]);

        return redirect()->route($this->routePrefix().'requests.thanks', $item->reference);
    }

    public function thanks(string $reference): View
    {
        $request = ServiceRequest::query()->where('reference', $reference)->first();
        abort_if($request === null, 404);

        return view('storefront.requests.thanks', ['reference' => $request->reference]);
    }

    private function reference(): string
    {
        do {
            $reference = 'SR-'.Str::upper(Str::random(14));
        } while (ServiceRequest::query()->where('reference', $reference)->exists());

        return $reference;
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
