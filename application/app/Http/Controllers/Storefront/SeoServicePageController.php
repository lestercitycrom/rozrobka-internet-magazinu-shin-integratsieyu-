<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use Illuminate\View\View;

final class SeoServicePageController extends Controller
{
    public function __invoke(string $canonicalPath, PublishedServicePageReadService $services): View
    {
        $page = ServicePage::query()
            ->where('publication_status', ServicePagePublicationStatus::Published->value)
            ->where('canonical_path', $canonicalPath)
            ->first();

        abort_if($page === null, 404);

        $locale = app()->getLocale() === 'ru'
            ? ServicePageLocale::Russian
            : ServicePageLocale::NorwegianBokmal;
        $service = $services->findBySlug($page->slug, $locale);
        abort_if($service === null, 404);

        $canonicalUrl = route('storefront.seo-services.show', $page->canonical_path);

        return view('storefront.services.show', compact('service', 'canonicalUrl'));
    }
}
