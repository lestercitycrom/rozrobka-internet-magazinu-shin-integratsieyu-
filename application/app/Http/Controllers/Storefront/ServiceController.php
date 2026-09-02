<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use App\Modules\Content\ReadModel\ServiceMediaCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(PublishedServicePageReadService $services, ServiceMediaCatalog $media): View
    {
        $items = $services->list($this->locale());

        return view('storefront.services.index', [
            'services' => $items,
            'serviceMedia' => $media->inventory($items),
        ]);
    }

    public function show(string $slug, PublishedServicePageReadService $services, ServiceMediaCatalog $media): View|RedirectResponse
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

        return view('storefront.services.show', [
            'service' => $service,
            'serviceMedia' => $media->for($service),
        ]);
    }

    private function locale(): ServicePageLocale
    {
        return app()->getLocale() === 'ru' ? ServicePageLocale::Russian : ServicePageLocale::NorwegianBokmal;
    }
}
