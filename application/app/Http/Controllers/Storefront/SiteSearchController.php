<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\ReadModel\SiteContentSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SiteSearchController extends Controller
{
    public function __invoke(Request $request, SiteContentSearch $search): View
    {
        $rawQuery = $request->query('q', '');
        $query = is_string($rawQuery) || is_numeric($rawQuery)
            ? mb_substr(trim((string) $rawQuery), 0, 100)
            : '';
        $locale = app()->getLocale() === 'ru'
            ? ServicePageLocale::Russian
            : ServicePageLocale::NorwegianBokmal;

        return view('storefront.search.index', [
            'query' => $query,
            'results' => $search->search($query, $locale),
        ]);
    }
}
