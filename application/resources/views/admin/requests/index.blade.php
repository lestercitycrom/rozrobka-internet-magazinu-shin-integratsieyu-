<x-layouts.admin :title="__('service_requests.title')">
    <div class="space-y-8">
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('service_requests.title') }}</h1>
            <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('service_requests.intro') }}</p>
        </header>

        @if ($requests->isEmpty())
            <p class="rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center text-easytire-muted">{{ __('service_requests.empty') }}</p>
        @else
            @php
                $statusTone = [
                    'new' => 'attention',
                    'contacted' => 'info',
                    'closed' => 'success',
                    'cancelled' => 'neutral',
                ];
            @endphp
            <div class="space-y-3 md:hidden">
                @foreach ($requests as $item)
                    <article class="easytire-card rounded-2xl p-5"><div class="flex items-start justify-between gap-3"><a class="font-black underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.show', $item) }}">{{ $item->reference }}</a><span class="et-admin-status et-admin-status--{{ $statusTone[$item->status->value] ?? 'neutral' }}">{{ __('service_requests.status_'.$item->status->value) }}</span></div><p class="mt-3 font-bold">{{ $item->name }}</p><p class="mt-1 break-words text-sm text-easytire-muted">{{ $item->email }} · {{ $item->phone }}</p><p class="mt-4 text-sm text-easytire-muted">{{ $item->created_at->format('Y-m-d H:i') }}</p></article>
                @endforeach
            </div>
            <div class="easytire-card hidden overflow-x-auto rounded-2xl md:block"><table class="min-w-full text-left text-sm"><thead class="bg-easytire-ink text-white"><tr><th class="p-4">{{ __('service_requests.reference') }}</th><th class="p-4">{{ __('service_requests.customer') }}</th><th class="p-4">{{ __('service_requests.service') }}</th><th class="p-4">{{ __('service_requests.status') }}</th><th class="p-4">{{ __('service_requests.received') }}</th></tr></thead><tbody class="divide-y divide-easytire-border">@foreach ($requests as $item)<tr class="transition hover:bg-easytire-brand-soft/50"><td class="p-4 font-bold"><a class="underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.show', $item) }}">{{ $item->reference }}</a></td><td class="p-4"><p class="font-bold">{{ $item->name }}</p><p class="mt-1 text-easytire-muted">{{ $item->email }}</p></td><td class="p-4 text-easytire-muted">{{ $serviceTitles[$item->service_slug] ?? $item->service_slug }}</td><td class="p-4"><span class="et-admin-status et-admin-status--{{ $statusTone[$item->status->value] ?? 'neutral' }}">{{ __('service_requests.status_'.$item->status->value) }}</span></td><td class="p-4 text-easytire-muted">{{ $item->created_at->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody></table></div>
            <div>{{ $requests->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
