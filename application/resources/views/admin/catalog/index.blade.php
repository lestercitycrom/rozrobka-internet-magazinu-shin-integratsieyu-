<x-layouts.admin :title="__('admin.catalog.title')">
    <div class="space-y-8">
        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">{{ __('admin.brand') }}</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('admin.catalog.heading') }}</h1>
                <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('admin.catalog.intro') }}</p>
            </div>
            <a class="easytire-interactive inline-flex min-h-11 items-center justify-center rounded-xl bg-easytire-brand px-5 text-sm font-black text-white hover:bg-easytire-brand-strong" href="{{ route('admin.catalog.publication.preview') }}">
                {{ __('catalog.bulk_preview') }}
            </a>
        </header>

        <form class="easytire-card grid gap-4 rounded-2xl p-5 md:grid-cols-4 md:p-6" method="GET">
            <label class="text-sm font-bold md:col-span-2">
                {{ __('admin.catalog.search') }}
                <input name="q" value="{{ $filters['q'] ?? '' }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm" type="search">
            </label>
            <label class="text-sm font-bold">
                {{ __('admin.catalog.type') }}
                <select name="type" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm"><option value="">{{ __('admin.catalog.all') }}</option><option value="tire" @selected(($filters['type'] ?? '') === 'tire')>Tire</option><option value="wheel" @selected(($filters['type'] ?? '') === 'wheel')>Wheel</option></select>
            </label>
            <label class="text-sm font-bold">
                {{ __('admin.catalog.status') }}
                <input name="status" value="{{ $filters['status'] ?? '' }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm">
            </label>
            <div class="md:col-span-4 flex flex-wrap items-center justify-between gap-3 border-t border-easytire-border pt-4">
                <p class="text-sm text-easytire-muted">{{ __('admin.catalog.heading') }}</p>
                <button class="min-h-11 rounded-lg bg-easytire-ink px-5 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" type="submit">{{ __('admin.catalog.search') }}</button>
            </div>
        </form>

        @if ($products->isEmpty())
            <div class="rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center text-easytire-muted"><p>{{ __('admin.catalog.empty') }}</p></div>
        @else
            <div class="easytire-card overflow-x-auto rounded-2xl">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-easytire-ink text-white"><tr><th class="p-4 font-bold">{{ __('admin.catalog.product') }}</th><th class="p-4 font-bold">{{ __('admin.catalog.brand') }}</th><th class="p-4 font-bold">{{ __('admin.catalog.type') }}</th><th class="p-4 font-bold">{{ __('admin.catalog.skus') }}</th><th class="p-4 font-bold">{{ __('admin.catalog.seen') }}</th></tr></thead>
                    <tbody class="divide-y divide-easytire-border">
                        @foreach ($products as $product)
                            <tr class="transition hover:bg-easytire-brand-soft/50">
                                <td class="p-4 font-bold"><a class="rounded underline decoration-2 underline-offset-4 hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" href="{{ route('admin.catalog.show', $product) }}">{{ $product->title }}</a></td>
                                <td class="p-4 text-easytire-muted">{{ $product->brand?->canonical_name }}</td><td class="p-4 text-easytire-muted">{{ $product->product_type->value }}</td><td class="p-4 font-bold">{{ $product->supplier_skus_count }}</td><td class="p-4 text-easytire-muted">{{ $product->last_catalog_seen_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pt-1">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
