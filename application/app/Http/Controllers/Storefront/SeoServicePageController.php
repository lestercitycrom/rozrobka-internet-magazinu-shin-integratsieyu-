<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\PublicServicePrice;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use App\Modules\Content\ReadModel\ServiceMediaCatalog;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class SeoServicePageController extends Controller
{
    public function __invoke(string $canonicalPath, PublishedServicePageReadService $services, ServiceMediaCatalog $media): View
    {
        abort_unless(Schema::hasTable('service_pages'), 404);

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
        $categories = match ($page->slug) {
            'mobilt-dekkskift' => ['workshop', 'mobile'],
            'dekkhotell' => ['hotel'],
            'omlegging-balansering' => ['mounting'],
            default => [],
        };
        $servicePrices = PublicServicePrice::query()
            ->published()
            ->whereIn('category_code', $categories)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category_code');

        return view('storefront.services.show', [
            'service' => $service,
            'canonicalUrl' => $canonicalUrl,
            'servicePrices' => $servicePrices,
            'serviceMedia' => $media->for($service),
        ]);
    }
}
