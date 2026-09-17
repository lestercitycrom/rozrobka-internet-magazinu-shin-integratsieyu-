<?php

namespace App\Modules\Content\ReadModel;

use App\Modules\Commerce\Models\PublicServicePrice;
use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\HomepageServiceCollection;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Models\ContentPage;
use App\Modules\Content\Models\HomepagePromotion;
use App\Modules\Content\Models\HomepageServiceCard;
use App\Support\PublicEditorialUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class SiteContentSearch
{
    private const MAX_RESULTS = 24;

    /** @return Collection<int, array{type: string, title: string, excerpt: string, url: string}> */
    public function search(string $query, ServicePageLocale $locale): Collection
    {
        $needle = $this->normalize(mb_substr(trim($query), 0, 100));
        if ($needle === '') {
            return collect();
        }

        return $this->index($locale)
            ->map(function (array $item) use ($needle): array {
                $item['score'] = $this->score($needle, $item);

                return $item;
            })
            ->filter(static fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(self::MAX_RESULTS)
            ->map(static fn (array $item): array => collect($item)->only(['type', 'title', 'excerpt', 'url'])->all())
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function index(ServicePageLocale $locale): Collection
    {
        $prefix = $locale === ServicePageLocale::Russian ? 'storefront.preview.' : 'storefront.';
        $suffix = $locale->value;
        $items = collect();
        $publishedServiceSlugs = collect();

        if (Schema::hasTable('service_pages')) {
            $services = app(PublishedServicePageReadService::class)->list($locale);
            $publishedServiceSlugs = $services->pluck('slug');

            foreach ($services as $service) {
                if (! filled($service->title)) {
                    continue;
                }

                $items->push($this->item(
                    type: 'service',
                    title: $service->title,
                    excerpt: $service->excerpt,
                    url: route(
                        $prefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'),
                        $service->canonicalPath ?: $service->slug,
                    ),
                    content: implode(' ', [$service->body, $service->seoTitle, $service->seoDescription]),
                    aliases: __('customer_search.service_aliases.'.$service->slug),
                ));
            }
        }

        foreach ((array) __('customer_search.service_fallbacks') as $slug => $fallback) {
            if ($publishedServiceSlugs->contains($slug) || ! is_array($fallback)) {
                continue;
            }

            $items->push($this->item(
                type: 'service',
                title: (string) ($fallback['title'] ?? ''),
                excerpt: (string) ($fallback['excerpt'] ?? ''),
                url: route($prefix.'services.index').'#'.(string) ($fallback['anchor'] ?? ''),
                aliases: $fallback['aliases'] ?? [],
            ));
        }

        $publishedContentSlugs = collect();
        if (Schema::hasTable('content_pages')) {
            ContentPage::query()
                ->whereIn('slug', ['om-oss', 'kontakt', 'personvern', 'kjopsvilkar'])
                ->where('publication_status', ContentPageStatus::Published->value)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->each(function (ContentPage $page) use ($items, $prefix, $suffix, $publishedContentSlugs): void {
                    $title = (string) $page->getAttribute("title_{$suffix}");
                    if (! filled($title)) {
                        return;
                    }

                    $publishedContentSlugs->push($page->slug);
                    $items->push($this->item(
                        type: 'page',
                        title: $title,
                        excerpt: (string) $page->getAttribute("excerpt_{$suffix}"),
                        url: route($prefix.'content.show', $page->slug),
                        content: implode(' ', [
                            $page->getAttribute("body_{$suffix}"),
                            $page->getAttribute("meta_title_{$suffix}"),
                            $page->getAttribute("meta_description_{$suffix}"),
                        ]),
                    ));
                });
        }

        $this->appendHomepageItems($items, $prefix, $suffix, $publishedContentSlugs);

        $items->push($this->item(
            type: 'price',
            title: __('customer_search.pricing_title'),
            excerpt: __('public_pricing.description'),
            url: route($prefix.'pricing.index'),
            aliases: __('customer_search.pricing_aliases'),
        ));

        if (Schema::hasTable('public_service_prices')) {
            PublicServicePrice::query()
                ->published()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->each(function (PublicServicePrice $price) use ($items, $prefix, $suffix): void {
                    $title = (string) $price->getAttribute("title_{$suffix}");
                    if (! filled($title)) {
                        return;
                    }

                    $category = (string) $price->getAttribute("category_title_{$suffix}");
                    $description = (string) $price->getAttribute("description_{$suffix}");

                    $items->push($this->item(
                        type: 'price',
                        title: $title,
                        excerpt: trim($category.($description !== '' ? ' · '.$description : '')),
                        url: route($prefix.'pricing.index').'#price-'.Str::slug($price->code),
                        content: implode(' ', [$price->code, $price->category_code, $category]),
                        aliases: __('customer_search.pricing_aliases'),
                    ));
                });
        }

        $items->push($this->item(
            type: 'store',
            title: __('customer_search.tyre_finder_title'),
            excerpt: __('customer_search.tyre_finder_excerpt'),
            url: route($prefix.'store.index'),
            aliases: __('customer_search.tyre_aliases'),
        ));

        return $items;
    }

    /** @param Collection<int, array<string, mixed>> $items
     * @param  Collection<int, string>  $publishedContentSlugs
     */
    private function appendHomepageItems(Collection $items, string $prefix, string $suffix, Collection $publishedContentSlugs): void
    {
        $home = route($prefix.'home');
        $settings = app(StorefrontSettingsReadService::class)->current();

        $items->push($this->item(
            type: 'homepage',
            title: __('customer_search.offers_title'),
            excerpt: __('customer_search.offers_excerpt'),
            url: $home.'#aktuelle-tilbud',
            aliases: __('customer_search.offers_aliases'),
        ));

        if (Schema::hasTable('homepage_promotions')) {
            HomepagePromotion::query()
                ->published()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->filter(fn (HomepagePromotion $promotion): bool => filled($promotion->getAttribute("title_{$suffix}"))
                    && filled($promotion->getAttribute("body_{$suffix}"))
                    && filled($promotion->getAttribute("cta_label_{$suffix}"))
                    && PublicEditorialUrl::isValid($promotion->cta_url))
                ->each(function (HomepagePromotion $promotion) use ($items, $home, $suffix): void {
                    $items->push($this->item(
                        type: 'homepage',
                        title: (string) $promotion->getAttribute("title_{$suffix}"),
                        excerpt: (string) $promotion->getAttribute("short_body_{$suffix}"),
                        url: $home.'#aktuelle-tilbud',
                        content: (string) $promotion->getAttribute("body_{$suffix}"),
                    ));
                });
        }

        if (Schema::hasTable('homepage_service_cards')) {
            foreach (HomepageServiceCollection::cases() as $collection) {
                $anchor = $collection === HomepageServiceCollection::Additional
                    ? 'home-additional-services-title'
                    : 'home-wheel-services-title';
                HomepageServiceCard::query()
                    ->publishedFor($collection)
                    ->with('servicePage')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->filter(fn (HomepageServiceCard $card): bool => filled($card->getAttribute("title_{$suffix}"))
                        && filled($card->getAttribute("description_{$suffix}"))
                        && filled($card->getAttribute("cta_label_{$suffix}"))
                        && ($card->servicePage !== null || PublicEditorialUrl::isValid($card->cta_url)))
                    ->each(function (HomepageServiceCard $card) use ($items, $home, $anchor, $suffix): void {
                        $items->push($this->item(
                            type: 'homepage',
                            title: (string) $card->getAttribute("title_{$suffix}"),
                            excerpt: (string) $card->getAttribute("description_{$suffix}"),
                            url: $home.'#'.$anchor,
                        ));
                    });
            }
        }

        foreach ((array) __('customer_search.home_sections') as $section) {
            if (! is_array($section)) {
                continue;
            }

            $target = ($section['contact'] ?? false) && $publishedContentSlugs->contains('kontakt')
                ? route($prefix.'content.show', 'kontakt')
                : $home.'#'.(string) ($section['anchor'] ?? '');
            $titleBase = (string) ($section['settings_title'] ?? '');
            $excerptBase = (string) ($section['settings_excerpt'] ?? '');
            $configuredTitle = $titleBase !== '' ? $settings?->getAttribute("{$titleBase}_{$suffix}") : null;
            $configuredExcerpt = $excerptBase !== '' ? $settings?->getAttribute("{$excerptBase}_{$suffix}") : null;
            $items->push($this->item(
                type: 'homepage',
                title: filled($configuredTitle) ? (string) $configuredTitle : (string) ($section['title'] ?? ''),
                excerpt: filled($configuredExcerpt) ? (string) $configuredExcerpt : (string) ($section['excerpt'] ?? ''),
                url: $target,
                aliases: $section['aliases'] ?? [],
            ));
        }
    }

    /** @param array<int, string>|string $aliases
     * @return array<string, mixed>
     */
    private function item(string $type, string $title, string $excerpt, string $url, string $content = '', array|string $aliases = []): array
    {
        $aliases = is_array($aliases) ? $aliases : [];
        $plainContent = strip_tags(Str::markdown($content));

        return [
            'type' => $type,
            'title' => $title,
            'excerpt' => $excerpt,
            'url' => $url,
            'normalized_title' => $this->normalize($title),
            'normalized_aliases' => array_values(array_filter(array_map($this->normalize(...), $aliases))),
            'normalized_text' => $this->normalize(implode(' ', [$title, $excerpt, $plainContent, ...$aliases])),
        ];
    }

    /** @param array<string, mixed> $item */
    private function score(string $needle, array $item): int
    {
        if ($needle === $item['normalized_title'] || in_array($needle, $item['normalized_aliases'], true)) {
            return 100;
        }

        $tokens = array_values(array_filter(explode(' ', $needle)));
        if ($tokens === [] || ! collect($tokens)->every(fn (string $token): bool => str_contains($item['normalized_text'], $token))) {
            return 0;
        }

        $score = 40;
        if (str_contains($item['normalized_title'], $needle)) {
            $score += 30;
        }
        if (collect($item['normalized_aliases'])->contains(fn (string $alias): bool => str_contains($alias, $needle))) {
            $score += 20;
        }

        return $score;
    }

    private function normalize(string $value): string
    {
        $normalized = Str::ascii(mb_strtolower($value));
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $normalized));
    }
}
