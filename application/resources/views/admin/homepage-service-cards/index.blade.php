<x-layouts.admin :title="__('admin.homepage_service_cards.title')">
    <div class="min-w-0 space-y-8">
        <header class="flex flex-col items-start gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">{{ __('admin.homepage_service_cards.collection_'.$collection->value) }}</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('admin.homepage_service_cards.heading') }}</h1>
                <p class="mt-2 max-w-2xl leading-7 text-easytire-muted">{{ __('admin.homepage_service_cards.intro') }}</p>
            </div>
            <a class="inline-flex min-h-11 shrink-0 items-center rounded-lg bg-easytire-brand px-4 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong" href="{{ route('admin.homepage-service-collections.create', $collection->value) }}">{{ __('admin.homepage_service_cards.new') }}</a>
        </header>

        <nav class="flex flex-wrap gap-2" aria-label="{{ __('admin.homepage_service_cards.collections') }}">
            @foreach (\App\Modules\Content\Enums\HomepageServiceCollection::cases() as $candidate)
                <a @class(['min-h-11 border px-4 py-3 text-sm font-bold', 'border-easytire-brand bg-easytire-brand text-white' => $candidate === $collection, 'border-easytire-border bg-white text-easytire-ink' => $candidate !== $collection]) href="{{ route('admin.homepage-service-collections.index', $candidate->value) }}" @if($candidate === $collection) aria-current="page" @endif>{{ __('admin.homepage_service_cards.collection_'.$candidate->value) }}</a>
            @endforeach
        </nav>

        @if (session('status'))<p class="border-l-4 border-easytire-brand bg-easytire-brand-soft p-4 text-sm font-bold" role="status">{{ session('status') }}</p>@endif

        <p class="border border-easytire-border bg-easytire-surface p-4 text-sm text-easytire-muted">{{ __('admin.homepage_service_cards.order_hint') }}</p>
        <div class="w-full max-w-[calc(100vw-2rem)] overflow-hidden border border-easytire-border bg-easytire-panel md:max-w-full">
            <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain" tabindex="0" role="region" aria-label="{{ __('admin.homepage_service_cards.table_label') }}">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <thead class="bg-easytire-brand-soft"><tr><th class="p-4" scope="col">{{ __('admin.homepage_service_cards.order') }}</th><th class="p-4" scope="col">{{ __('admin.homepage_service_cards.card') }}</th><th class="p-4" scope="col">{{ __('admin.homepage_service_cards.service') }}</th><th class="p-4" scope="col">{{ __('admin.homepage_service_cards.media') }}</th><th class="p-4" scope="col">{{ __('admin.homepage_service_cards.status') }}</th><th class="p-4" scope="col">{{ __('admin.homepage_service_cards.actions') }}</th></tr></thead>
                    <tbody class="divide-y divide-easytire-border">
                        @forelse ($cards as $card)
                            <tr class="transition hover:bg-easytire-brand-soft/50" data-testid="homepage-service-card-row">
                                <td class="p-4 font-bold">{{ $card->sort_order }}</td>
                                <td class="p-4"><strong class="block">{{ $card->title_nb }}</strong><span class="mt-1 block max-w-md text-xs text-easytire-muted">{{ Str::limit($card->description_nb, 100) }}</span></td>
                                <td class="p-4">{{ $card->servicePage?->title_nb ?: __('admin.homepage_service_cards.custom_destination') }}</td>
                                <td class="p-4">@if($card->mediaAsset)<img class="size-12 border border-easytire-border object-cover" src="{{ Storage::disk($card->mediaAsset->disk)->url($card->mediaAsset->path) }}" alt="" loading="lazy">@else<span class="text-xs text-easytire-muted">{{ __('admin.homepage_service_cards.no_media') }}</span>@endif</td>
                                <td class="p-4"><span class="et-admin-status et-admin-status--{{ $card->publication_status->value === 'published' ? 'success' : 'neutral' }}">{{ __('content_ui.status_'.$card->publication_status->value) }}</span></td>
                                <td class="p-4 text-right"><a class="font-semibold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.homepage-service-collections.edit', [$collection->value, $card]) }}">{{ __('admin.homepage_service_cards.edit') }}</a></td>
                            </tr>
                        @empty
                            <tr><td class="p-6 text-easytire-muted" colspan="6">{{ __('admin.homepage_service_cards.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $cards->links() }}
    </div>
</x-layouts.admin>
