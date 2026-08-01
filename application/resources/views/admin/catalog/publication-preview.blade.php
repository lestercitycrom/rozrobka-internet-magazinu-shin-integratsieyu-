<x-layouts.admin :title="__('catalog.bulk_preview')">
    <div class="mx-auto max-w-6xl space-y-6">
        <a class="inline-flex items-center gap-2 text-sm font-bold text-easytire-ink underline decoration-easytire-brand/40 underline-offset-4 transition hover:decoration-easytire-brand" href="{{ route('admin.catalog.index') }}">
            <span aria-hidden="true">←</span> {{ __('admin.catalog.back') }}
        </a>

        <header class="easytire-card flex flex-col gap-4 rounded-2xl p-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-3xl">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-easytire-brand">EasyTire catalog</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">{{ __('catalog.bulk_preview') }}</h1>
                <p class="mt-3 text-easytire-muted">{{ __('catalog.bulk_help') }}</p>
            </div>
            <span class="inline-flex min-w-20 items-center justify-center rounded-full bg-easytire-ink px-4 py-2 text-lg font-black text-white">
                {{ number_format($candidates->count(), 0, ',', ' ') }}
            </span>
        </header>

        @if($candidates->isEmpty())
            <p class="easytire-card rounded-2xl border-dashed p-8 text-easytire-muted">{{ __('catalog.bulk_empty') }}</p>
        @else
            <div class="easytire-card overflow-hidden rounded-2xl">
                <div class="max-h-[34rem] overflow-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="sticky top-0 z-10 bg-easytire-soft text-xs uppercase tracking-wider text-easytire-muted">
                            <tr>
                                <th class="p-4">{{ __('admin.catalog.product') }}</th>
                                <th class="p-4">{{ __('admin.catalog.brand') }}</th>
                                <th class="p-4">{{ __('catalog.preview_retail') }}</th>
                                <th class="p-4">{{ __('catalog.source_price') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($candidates->take(100) as $candidate)
                                <tr class="border-t border-easytire-border transition hover:bg-easytire-soft/70">
                                    <td class="p-4 font-bold text-easytire-ink">{{ $candidate['product']->title }}</td>
                                    <td class="p-4 text-easytire-muted">{{ $candidate['product']->brand->canonical_name }}</td>
                                    <td class="whitespace-nowrap p-4 font-black">{{ number_format($candidate['price']->money->minor / 100, 2, ',', ' ') }} {{ $candidate['price']->money->currency }}</td>
                                    <td class="p-4 text-easytire-muted">{{ $candidate['price']->sourcePriceListCode }} · {{ __('catalog.gross_source') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($candidates->count() > 100)
                    <p class="border-t border-easytire-border bg-easytire-soft px-4 py-3 text-center text-sm font-bold text-easytire-muted">
                        + {{ number_format($candidates->count() - 100, 0, ',', ' ') }}
                    </p>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.catalog.publication.publish') }}" class="flex justify-end">
                @csrf
                @method('PUT')
                <button class="easytire-interactive min-h-12 rounded-xl bg-easytire-brand px-6 font-black text-white hover:bg-easytire-brand-strong" type="submit">
                    {{ trans_choice('catalog.bulk_confirm', $candidates->count(), ['count' => $candidates->count()]) }}
                </button>
            </form>
        @endif
    </div>
</x-layouts.admin>
