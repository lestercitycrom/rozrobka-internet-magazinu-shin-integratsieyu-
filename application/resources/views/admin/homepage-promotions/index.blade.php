<x-layouts.admin :title="__('admin.homepage_promotions.title')">
    <div class="min-w-0 space-y-8">
        <header class="flex flex-col items-start gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('admin.homepage_promotions.heading') }}</h1>
                <p class="mt-2 max-w-2xl leading-7 text-easytire-muted">{{ __('admin.homepage_promotions.intro') }}</p>
            </div>
            <a class="inline-flex min-h-11 shrink-0 items-center rounded-lg bg-easytire-brand px-4 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong" href="{{ route('admin.homepage-promotions.create') }}">{{ __('admin.homepage_promotions.new') }}</a>
        </header>

        <p class="rounded-xl border border-easytire-border bg-easytire-surface p-4 text-sm text-easytire-muted">{{ __('admin.homepage_promotions.empty_hint') }}</p>

        <div class="hidden w-full overflow-hidden rounded-2xl border border-easytire-border bg-easytire-panel md:block">
            <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain" tabindex="0" role="region" aria-label="{{ __('admin.homepage_promotions.table_label') }}">
                <table class="w-full min-w-[42rem] text-left text-sm">
                    <thead class="bg-easytire-brand-soft"><tr><th class="p-4" scope="col">{{ __('admin.homepage_promotions.order') }}</th><th class="p-4" scope="col">{{ __('admin.homepage_promotions.title_nb') }}</th><th class="p-4" scope="col">{{ __('admin.homepage_promotions.media') }}</th><th class="p-4" scope="col">{{ __('admin.homepage_promotions.status') }}</th><th class="p-4" scope="col">{{ __('admin.homepage_promotions.actions') }}</th></tr></thead>
                    <tbody class="divide-y divide-easytire-border">
                        @forelse ($promotions as $promotion)
                            <tr class="transition hover:bg-easytire-brand-soft/50"><td class="p-4">{{ $promotion->sort_order }}</td><td class="p-4 font-bold">{{ $promotion->title_nb }}</td><td class="p-4">@if($promotion->mediaAsset)<img class="size-10 rounded-md border border-easytire-border object-cover" src="{{ Storage::disk($promotion->mediaAsset->disk)->url($promotion->mediaAsset->path) }}" alt="" loading="lazy"><span class="sr-only">{{ __('admin.homepage_promotions.media_selected') }}</span>@elseif($promotion->media_asset_id)<span class="text-xs text-easytire-muted">{{ __('admin.homepage_promotions.unavailable_media') }}</span>@else<span class="text-xs text-easytire-muted">{{ __('admin.homepage_promotions.no_media') }}</span>@endif</td><td class="p-4"><span class="et-admin-status et-admin-status--{{ $promotion->publication_status->value === 'published' ? 'success' : 'neutral' }}">{{ __('content_ui.status_'.$promotion->publication_status->value) }}</span></td><td class="p-4 text-right"><a class="font-semibold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.homepage-promotions.edit', $promotion) }}">{{ __('admin.homepage_promotions.edit') }}</a></td></tr>
                        @empty
                            <tr><td class="p-6 text-easytire-muted" colspan="5">{{ __('admin.homepage_promotions.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="et-admin-mobile-records md:hidden" data-testid="homepage-promotions-mobile-list">
            @forelse ($promotions as $promotion)
                <article class="et-admin-mobile-record">
                    <div class="et-admin-mobile-record__head">
                        <strong>{{ $promotion->title_nb }}</strong>
                        <a class="et-admin-mobile-record__action" href="{{ route('admin.homepage-promotions.edit', $promotion) }}">{{ __('admin.homepage_promotions.edit') }}</a>
                    </div>
                    <dl>
                        <div><dt>{{ __('admin.homepage_promotions.order') }}</dt><dd>{{ $promotion->sort_order }}</dd></div>
                        <div><dt>{{ __('admin.homepage_promotions.status') }}</dt><dd><span class="et-admin-status et-admin-status--{{ $promotion->publication_status->value === 'published' ? 'success' : 'neutral' }}">{{ __('content_ui.status_'.$promotion->publication_status->value) }}</span></dd></div>
                    </dl>
                </article>
            @empty
                <p class="et-admin-mobile-record">{{ __('admin.homepage_promotions.empty') }}</p>
            @endforelse
        </div>
        {{ $promotions->links() }}
    </div>
</x-layouts.admin>
