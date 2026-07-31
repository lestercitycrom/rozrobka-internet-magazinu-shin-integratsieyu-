<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(PublishedServicePageReadService $services): View
    {
        return view('storefront.services.index', ['services' => $services->list($this->locale())]);
    }

    public function show(string $slug, PublishedServicePageReadService $services): View
    {
        $service = $services->findBySlug($slug, $this->locale());
        abort_if($service === null, 404);

        return view('storefront.services.show', compact('service'));
    }

    private function locale(): ServicePageLocale
    {
        return app()->getLocale() === 'ru' ? ServicePageLocale::Russian : ServicePageLocale::NorwegianBokmal;
    }
}
