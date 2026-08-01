<?php

namespace App\Modules\Content\ReadModel;

use App\Modules\Content\Enums\ContentPageStatus;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\Models\ContentPage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class PublishedContentPageReadService
{
    /** @param array<int, string> $slugs
     * @return array<string, true>
     */
    public function publishedSlugs(array $slugs): array
    {
        if (! Schema::hasTable('content_pages')) {
            return [];
        }

        return ContentPage::query()
            ->whereIn('slug', $slugs)
            ->where('publication_status', ContentPageStatus::Published->value)
            ->pluck('slug')
            ->mapWithKeys(fn (string $slug): array => [$slug => true])
            ->all();
    }

    public function findBySlug(string $slug, ServicePageLocale $locale = ServicePageLocale::NorwegianBokmal): ?ContentPageReadItem
    {
        $page = ContentPage::query()->with('mediaAssignments.asset')->where('slug', $slug)->where('publication_status', ContentPageStatus::Published->value)->first();
        if ($page === null) {
            return null;
        }
        $suffix = $locale->value;
        $asset = $page->mediaAssignments->firstWhere('role', 'hero')?->asset;
        $hero = $asset === null ? null : ['url' => Storage::disk($asset->disk)->url($asset->path), 'alt' => $asset->getAttribute("alt_{$suffix}") ?: $asset->alt_nb ?: ''];

        return new ContentPageReadItem($page->slug, $page->getAttribute("title_{$suffix}"), $page->getAttribute("excerpt_{$suffix}"), $page->getAttribute("body_{$suffix}"), $page->getAttribute("meta_title_{$suffix}"), $page->getAttribute("meta_description_{$suffix}"), $hero);
    }
}
