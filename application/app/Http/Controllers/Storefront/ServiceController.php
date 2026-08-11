<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(PublishedServicePageReadService $services): View
    {
        return view('storefront.services.index', ['services' => $services->list($this->locale())]);
    }

    public function show(string $slug, PublishedServicePageReadService $services): View|RedirectResponse
    {
        $service = $services->findBySlug($slug, $this->locale());
        abort_if($service === null, 404);

        $canonicalPath = ServicePage::query()->where('slug', $slug)->value('canonical_path');
        if (is_string($canonicalPath) && $canonicalPath !== '') {
            $route = app()->getLocale() === 'ru'
                ? 'storefront.preview.seo-services.show'
                : 'storefront.seo-services.show';

            return redirect()->route($route, $canonicalPath, 301);
        }

        return view('storefront.services.show', compact('service'));
    }

    private function locale(): ServicePageLocale
    {
        return app()->getLocale() === 'ru' ? ServicePageLocale::Russian : ServicePageLocale::NorwegianBokmal;
    }
}
