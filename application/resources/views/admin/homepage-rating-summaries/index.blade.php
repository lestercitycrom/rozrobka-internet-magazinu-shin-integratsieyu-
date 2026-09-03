<x-layouts.admin :title="__('admin.homepage_ratings.title')">
    <div class="min-w-0 space-y-8">
        <header class="flex flex-col items-start gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div><h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('admin.homepage_ratings.heading') }}</h1><p class="mt-2 max-w-2xl leading-7 text-easytire-muted">{{ __('admin.homepage_ratings.intro') }}</p></div>
            <a class="et-btn et-btn--primary" href="{{ route('admin.homepage-rating-summaries.create') }}">{{ __('admin.homepage_ratings.new') }}</a>
        </header>
        @if (session('status'))<p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif
        <div class="overflow-x-auto rounded-2xl border border-easytire-border bg-easytire-panel" tabindex="0" role="region" aria-label="{{ __('admin.homepage_ratings.table_label') }}">
            <table class="min-w-[42rem] text-left text-sm"><thead class="bg-easytire-brand-soft"><tr><th>{{ __('admin.homepage_ratings.order') }}</th><th>{{ __('admin.homepage_ratings.platform') }}</th><th>{{ __('admin.homepage_ratings.rating') }}</th><th>{{ __('admin.homepage_ratings.count') }}</th><th>{{ __('admin.homepage_ratings.status') }}</th><th>{{ __('admin.homepage_ratings.actions') }}</th></tr></thead>
                <tbody>@forelse ($summaries as $summary)<tr><td>{{ $summary->sort_order }}</td><td class="font-bold">{{ __('admin.homepage_ratings.platform_'.$summary->platform->value) }}</td><td>{{ $summary->rating }}</td><td>{{ $summary->count_label_nb ?: $summary->review_count ?: '—' }}</td><td><span class="et-admin-status et-admin-status--{{ $summary->publication_status->value === 'published' ? 'success' : 'neutral' }}">{{ __('content_ui.status_'.$summary->publication_status->value) }}</span></td><td><a class="font-bold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route('admin.homepage-rating-summaries.edit', $summary) }}">{{ __('admin.homepage_ratings.edit') }}</a></td></tr>@empty<tr><td colspan="6">{{ __('admin.homepage_ratings.empty') }}</td></tr>@endforelse</tbody>
            </table>
        </div>
        {{ $summaries->links() }}
    </div>
</x-layouts.admin>
