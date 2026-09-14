<x-layouts.admin :title="__('service_requests.title')">
    <div class="space-y-8">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('service_requests.title') }}</h1>
                <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('service_requests.intro') }}</p>
            </div>
            <a class="et-btn et-btn--primary" href="{{ route('admin.service-requests.create') }}" data-testid="request-create">{{ __('service_requests.create') }}</a>
        </header>

        <form class="easytire-card grid gap-4 rounded-2xl p-5 md:grid-cols-2 xl:grid-cols-7" method="get" action="{{ route('admin.service-requests.index') }}" data-testid="request-filters">
            <label class="block text-sm font-bold xl:col-span-2">{{ __('service_requests.search') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('service_requests.search_hint') }}"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.date_from') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="date_from" value="{{ $filters['date_from'] ?? ($filters['date'] ?? '') }}" type="date"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.date_to') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="date_to" value="{{ $filters['date_to'] ?? ($filters['date'] ?? '') }}" type="date"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.service') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="service"><option value="">{{ __('service_requests.all') }}</option>@foreach($serviceOptions as $service)<option value="{{ $service->slug }}" @selected(($filters['service'] ?? '') === $service->slug)>{{ app()->getLocale() === 'ru' && filled($service->title_ru) ? $service->title_ru : $service->title_nb }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.fulfilment') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="fulfilment"><option value="">{{ __('service_requests.all') }}</option>@foreach(['workshop','mobile'] as $value)<option value="{{ $value }}" @selected(($filters['fulfilment'] ?? '') === $value)>{{ __('service_requests.fulfilment_'.$value) }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.status') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="status"><option value="">{{ __('service_requests.all') }}</option>@foreach(['new','contacted','closed','cancelled'] as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __('service_requests.status_'.$value) }}</option>@endforeach</select></label>
            <div class="flex flex-wrap items-end gap-3 md:col-span-2 xl:col-span-7">
                <label class="flex min-h-11 items-center gap-3 text-sm font-bold"><input name="new_only" value="1" type="checkbox" @checked(request()->boolean('new_only'))>{{ __('service_requests.new_only') }}</label>
                <button class="et-btn et-btn--primary" type="submit">{{ __('service_requests.apply_filters') }}</button>
                <a class="et-btn et-btn--outline" href="{{ route('admin.service-requests.index') }}">{{ __('service_requests.reset_filters') }}</a>
            </div>
        </form>

        @if ($requests->isEmpty())
            <p class="rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center text-easytire-muted">{{ __('service_requests.empty') }}</p>
        @else
            @php($statusTone = ['new' => 'attention', 'contacted' => 'info', 'closed' => 'success', 'cancelled' => 'neutral'])
            <div class="space-y-3 md:hidden">
                @foreach ($requests as $item)
                    <article class="easytire-card rounded-2xl p-5">
                        <div class="flex items-start justify-between gap-3"><a class="font-black underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.show', $item) }}">{{ $item->reference }}</a><span class="et-admin-status et-admin-status--{{ $statusTone[$item->status->value] ?? 'neutral' }}">{{ __('service_requests.status_'.$item->status->value) }}</span></div>
                        <p class="mt-3 font-bold">{{ $item->name }}</p><p class="mt-1 break-words text-sm text-easytire-muted">{{ $item->email ?: __('service_requests.no_email') }} · {{ $item->phone }}</p>
                        <p class="mt-3 text-sm">{{ $serviceTitles[$item->service_slug] ?? $item->service_slug }}</p>
                        <p class="mt-3 text-sm text-easytire-muted">{{ $item->preferred_date?->format('Y-m-d') ?: __('service_requests.unconfirmed') }} · {{ $item->created_at->format('Y-m-d H:i') }}</p>
                    </article>
                @endforeach
            </div>
            <div class="easytire-card hidden overflow-x-auto rounded-2xl md:block">
                <table class="min-w-full text-left text-sm"><thead class="bg-easytire-ink text-white"><tr><th class="p-4">{{ __('service_requests.reference') }}</th><th class="p-4">{{ __('service_requests.customer') }}</th><th class="p-4">{{ __('service_requests.service') }}</th><th class="p-4">{{ __('service_requests.preferred_date') }}</th><th class="p-4">{{ __('service_requests.status') }}</th><th class="p-4">{{ __('service_requests.received') }}</th></tr></thead>
                    <tbody class="divide-y divide-easytire-border">@foreach ($requests as $item)<tr class="transition hover:bg-easytire-brand-soft/50"><td class="p-4 font-bold"><a class="underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.show', $item) }}">{{ $item->reference }}</a></td><td class="p-4"><p class="font-bold">{{ $item->name }}</p><p class="mt-1 text-easytire-muted">{{ $item->phone }}@if($item->email)<br>{{ $item->email }}@endif</p></td><td class="p-4 text-easytire-muted">{{ $serviceTitles[$item->service_slug] ?? $item->service_slug }}</td><td class="p-4">{{ $item->preferred_date?->format('Y-m-d') ?: __('service_requests.unconfirmed') }}<br><span class="text-easytire-muted">{{ $item->preferred_window }}</span></td><td class="p-4"><span class="et-admin-status et-admin-status--{{ $statusTone[$item->status->value] ?? 'neutral' }}">{{ __('service_requests.status_'.$item->status->value) }}</span></td><td class="p-4 text-easytire-muted">{{ $item->created_at->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody>
                </table>
            </div>
            <div>{{ $requests->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
