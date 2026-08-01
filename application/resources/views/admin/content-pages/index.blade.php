<x-layouts.admin :title="__('admin.content.title')">
    <div class="min-w-0 space-y-8">
        <header class="flex flex-col items-start gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('admin.content.heading') }}</h1>
                <p class="mt-2 max-w-2xl leading-7 text-easytire-muted">{{ __('admin.content.intro') }}</p>
            </div>
            <a class="inline-flex min-h-11 shrink-0 items-center rounded-lg bg-easytire-brand px-4 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong" href="{{ route('admin.content-pages.create') }}">
                {{ __('admin.content.new') }}
            </a>
        </header>

        <p class="rounded-xl border border-easytire-border bg-easytire-surface p-4 text-sm text-easytire-muted">{{ __('content_ui.publication_hint') }}</p>

        <div class="w-full max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-easytire-border bg-easytire-panel md:max-w-full">
            <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain" tabindex="0" role="region" aria-label="{{ __('admin.content.table_label') }}">
                <table class="w-full min-w-[36rem] text-left text-sm">
                <thead class="bg-easytire-brand-soft">
                    <tr>
                        <th class="p-4" scope="col">{{ __('admin.content.order') }}</th>
                        <th class="p-4" scope="col">{{ __('admin.content.title_nb') }}</th>
                        <th class="p-4" scope="col">{{ __('admin.content.status') }}</th>
                        <th class="p-4" scope="col">{{ __('admin.content.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-easytire-border">
                    @forelse ($pages as $page)
                        <tr class="transition hover:bg-easytire-brand-soft/50">
                            <td class="p-4">{{ $page->sort_order }}</td>
                            <td class="p-4 font-bold">{{ $page->title_nb }}</td>
                            <td class="p-4"><span class="rounded-full bg-easytire-brand-soft px-3 py-1 text-xs font-bold text-easytire-brand-strong">{{ __('content_ui.status_'.$page->publication_status->value) }}</span></td>
                            <td class="p-4 text-right"><a class="font-semibold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.content-pages.edit', $page) }}">{{ __('admin.content.edit') }}</a></td>
                        </tr>
                    @empty
                        <tr><td class="p-6 text-easytire-muted" colspan="4">{{ __('admin.content.empty') }}</td></tr>
                    @endforelse
                </tbody>
                </table>
            </div>
        </div>

        {{ $pages->links() }}
    </div>
</x-layouts.admin>
