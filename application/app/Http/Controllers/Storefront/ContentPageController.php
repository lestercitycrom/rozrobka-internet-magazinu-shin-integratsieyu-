<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\ReadModel\PublishedContentPageReadService;
use Illuminate\View\View;

final class ContentPageController extends Controller
{
    public function show(string $slug, PublishedContentPageReadService $pages): View
    {
        $page = $pages->findBySlug($slug, app()->getLocale() === 'ru' ? ServicePageLocale::Russian : ServicePageLocale::NorwegianBokmal);
        abort_if($page === null, 404);

        return view('storefront.content.show', compact('page'));
    }
}
