<?php

namespace App\Modules\Content\ReadModel;

use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use Illuminate\Support\Collection;

final class PublishedServicePageReadService
{
    /** @return Collection<int, ServicePageReadItem> */
    public function list(ServicePageLocale $locale = ServicePageLocale::NorwegianBokmal): Collection
    {
        return ServicePage::query()
            ->where('publication_status', ServicePagePublicationStatus::Published->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ServicePage $page) => $this->item($page, $locale));
    }

    public function findBySlug(string $slug, ServicePageLocale $locale = ServicePageLocale::NorwegianBokmal): ?ServicePageReadItem
    {
        $page = ServicePage::query()
            ->where('publication_status', ServicePagePublicationStatus::Published->value)
            ->where('slug', $slug)
            ->first();

        return $page === null ? null : $this->item($page, $locale);
    }

    private function item(ServicePage $page, ServicePageLocale $locale): ServicePageReadItem
    {
        $suffix = $locale->value;

        return new ServicePageReadItem(
            slug: $page->slug,
            title: $page->getAttribute("title_{$suffix}"),
            excerpt: $page->getAttribute("excerpt_{$suffix}"),
            body: $page->getAttribute("body_{$suffix}"),
            seoTitle: $page->getAttribute("seo_title_{$suffix}"),
            seoDescription: $page->getAttribute("seo_description_{$suffix}"),
        );
    }
}
