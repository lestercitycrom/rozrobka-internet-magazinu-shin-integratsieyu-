<x-layouts.admin :title="__('admin.services.title')">
    <div class="space-y-8">
        <header class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">{{ __('admin.services.heading') }}</h1>
                <p class="mt-2 text-easytire-muted">{{ __('admin.services.intro') }}</p>
            </div>
            <a class="bg-easytire-brand px-4 py-2 text-sm font-semibold text-white hover:bg-easytire-brand-strong focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand" href="{{ route('admin.services.create') }}">{{ __('admin.services.new') }}</a>
        </header>

        @if ($pages->isEmpty())
            <p class="border border-dashed border-easytire-border bg-easytire-panel p-8 text-easytire-muted">{{ __('admin.services.empty') }}</p>
        @else
            <div class="overflow-x-auto border border-easytire-border bg-easytire-panel">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-easytire-brand-soft">
                        <tr><th class="p-3">{{ __('admin.services.order') }}</th><th class="p-3">{{ __('admin.services.title_nb') }}</th><th class="p-3">{{ __('service_flows.label') }}</th><th class="p-3">{{ __('admin.services.status') }}</th><th class="p-3"><span class="sr-only">{{ __('admin.services.edit') }}</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pages as $page)
                            <tr class="border-t border-easytire-border">
                                <td class="p-3">{{ $page->sort_order }}</td>
                                <td class="p-3 font-medium">{{ $page->title_nb }}</td>
                                <td class="p-3">{{ __('service_flows.'.$page->request_flow->value) }}</td>
                                <td class="p-3">{{ __('services.statuses.'.$page->publication_status->value) }}</td>
                                <td class="p-3"><a class="underline underline-offset-4 hover:text-easytire-brand focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand" href="{{ route('admin.services.edit', $page) }}">{{ __('admin.services.edit') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $pages->links() }}
        @endif
    </div>
</x-layouts.admin>
