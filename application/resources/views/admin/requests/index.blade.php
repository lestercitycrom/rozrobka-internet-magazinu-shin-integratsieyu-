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

        <nav class="flex flex-wrap gap-2" aria-label="{{ __('service_requests.views') }}" data-testid="request-views">
            @foreach(['all', 'new', 'workshop', 'mobile', 'additional'] as $view)
                <a class="et-btn {{ $activeView === $view ? 'et-btn--primary' : 'et-btn--outline' }}" href="{{ route('admin.service-requests.index', $view === 'all' ? [] : ['view' => $view]) }}" @if($activeView === $view) aria-current="page" @endif>{{ __('service_requests.view_'.$view) }}</a>
            @endforeach
        </nav>

        <form class="easytire-card grid gap-4 rounded-2xl p-5 md:grid-cols-2 xl:grid-cols-4" method="get" action="{{ route('admin.service-requests.index') }}" data-testid="request-filters">
            @if($activeView !== 'all')<input type="hidden" name="view" value="{{ $activeView }}">@endif
            <label class="block text-sm font-bold md:col-span-2">{{ __('service_requests.search') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('service_requests.search_hint') }}"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.date_from') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="date_from" value="{{ $filters['date_from'] ?? ($filters['date'] ?? '') }}" type="date"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.date_to') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="date_to" value="{{ $filters['date_to'] ?? ($filters['date'] ?? '') }}" type="date"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.service') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="service"><option value="">{{ __('service_requests.all') }}</option>@foreach($serviceOptions as $service)<option value="{{ $service->slug }}" @selected(($filters['service'] ?? '') === $service->slug)>{{ app()->getLocale() === 'ru' && filled($service->title_ru) ? $service->title_ru : $service->title_nb }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.fulfilment') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="fulfilment"><option value="">{{ __('service_requests.all') }}</option>@foreach(['workshop','mobile'] as $value)<option value="{{ $value }}" @selected(($filters['fulfilment'] ?? '') === $value)>{{ __('service_requests.fulfilment_'.$value) }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.status') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="status"><option value="">{{ __('service_requests.all') }}</option>@foreach(['new','contacted','closed','cancelled'] as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __('service_requests.status_'.$value) }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.district') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="district" value="{{ $filters['district'] ?? '' }}"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.dekkhotell') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="dekkhotell"><option value="">{{ __('service_requests.all') }}</option><option value="1" @selected(($filters['dekkhotell'] ?? '') === '1')>{{ __('service_requests.yes') }}</option><option value="0" @selected(($filters['dekkhotell'] ?? '') === '0')>{{ __('service_requests.no') }}</option></select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.assigned_van') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="assigned_van"><option value="">{{ __('service_requests.all') }}</option>@foreach(['unassigned','van_1','van_2'] as $value)<option value="{{ $value }}" @selected(($filters['assigned_van'] ?? '') === $value)>{{ __('service_requests.assigned_van_'.$value) }}</option>@endforeach</select></label>
            <div class="flex flex-wrap items-end gap-3 md:col-span-2 xl:col-span-2">
                <button class="et-btn et-btn--primary" type="submit">{{ __('service_requests.apply_filters') }}</button>
                <a class="et-btn et-btn--outline" href="{{ route('admin.service-requests.index', $activeView === 'all' ? [] : ['view' => $activeView]) }}">{{ __('service_requests.reset_filters') }}</a>
            </div>
        </form>

        @if($requests->isEmpty())
            <div class="rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center"><p class="text-easytire-muted">{{ __('service_requests.empty_view') }}</p><a class="mt-4 inline-flex font-bold text-easytire-brand underline" href="{{ route('admin.service-requests.index') }}">{{ __('service_requests.show_all') }}</a></div>
        @else
            @php($statusTone = ['new' => 'attention', 'contacted' => 'info', 'closed' => 'success', 'cancelled' => 'neutral'])
            <div class="space-y-3 lg:hidden">
                @foreach($requests as $item)
                    <article class="easytire-card rounded-2xl p-5">
                        <div class="flex items-start justify-between gap-3"><a class="font-black underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.show', $item) }}">{{ $item->reference }}</a><span class="et-admin-status et-admin-status--{{ $statusTone[$item->status->value] ?? 'neutral' }}">{{ __('service_requests.status_'.$item->status->value) }}</span></div>
                        <p class="mt-3 font-bold">@if($item->client)<a class="underline" href="{{ route('admin.clients.show', $item->client) }}">{{ $item->name }}</a>@else{{ $item->name }}@endif</p>
                        <p class="mt-1 text-sm text-easytire-muted">{{ $item->phone }} · {{ $serviceTitles[$item->service_slug] ?? $item->service_slug }}</p>
                        <p class="mt-3 text-sm">{{ __('service_requests.fulfilment_'.$item->fulfilment) }}@if($item->district) · {{ $item->district }}@endif · {{ $item->dekkhotell ? __('service_requests.dekkhotell_short') : __('service_requests.no_dekkhotell') }}</p>
                        @if($item->fulfilment === 'mobile')<p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.assigned_van') }}: {{ __('service_requests.assigned_van_'.($item->assigned_van ?: 'unassigned')) }}</p>@endif
                        <p class="mt-3 text-sm text-easytire-muted">{{ $item->preferred_date?->format('Y-m-d') ?: __('service_requests.unconfirmed') }} · {{ $item->preferred_window ?: __('service_requests.unconfirmed') }}</p>
                        <p class="mt-2 text-xs text-easytire-muted">{{ __('service_requests.source_'.$item->source->value) }}@if($item->createdBy) · {{ $item->createdBy->name }}@endif · {{ $item->created_at->format('Y-m-d H:i') }}</p>
                    </article>
                @endforeach
            </div>
            <div class="easytire-card hidden overflow-x-auto rounded-2xl lg:block">
                <table class="min-w-[78rem] text-left text-sm">
                    <thead class="bg-easytire-ink text-white"><tr><th class="p-4">{{ __('service_requests.reference') }}</th><th class="p-4">{{ __('service_requests.customer') }}</th><th class="p-4">{{ __('service_requests.service') }}</th><th class="p-4">{{ __('service_requests.preferred_date') }}</th><th class="p-4">{{ __('service_requests.district') }}</th><th class="p-4">{{ __('service_requests.assigned_van') }}</th><th class="p-4">{{ __('service_requests.dekkhotell') }}</th><th class="p-4">{{ __('service_requests.status') }}</th><th class="p-4">{{ __('service_requests.source') }}</th><th class="p-4">{{ __('service_requests.received') }}</th></tr></thead>
                    <tbody class="divide-y divide-easytire-border">
                        @foreach($requests as $item)
                            <tr class="transition hover:bg-easytire-brand-soft/50">
                                <td class="p-4 font-bold"><a class="underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.show', $item) }}">{{ $item->reference }}</a></td>
                                <td class="p-4"><p class="font-bold">@if($item->client)<a class="underline" href="{{ route('admin.clients.show', $item->client) }}">{{ $item->name }}</a>@else{{ $item->name }}@endif</p><p class="mt-1 text-easytire-muted">{{ $item->phone }}</p></td>
                                <td class="p-4"><p>{{ $serviceTitles[$item->service_slug] ?? $item->service_slug }}</p><p class="mt-1 text-easytire-muted">{{ __('service_requests.fulfilment_'.$item->fulfilment) }}</p></td>
                                <td class="p-4">{{ $item->preferred_date?->format('Y-m-d') ?: __('service_requests.unconfirmed') }}<br><span class="text-easytire-muted">{{ $item->preferred_window ?: __('service_requests.unconfirmed') }}</span></td>
                                <td class="p-4 text-easytire-muted">{{ $item->district ?: '—' }}</td>
                                <td class="p-4 text-easytire-muted">{{ $item->fulfilment === 'mobile' ? __('service_requests.assigned_van_'.($item->assigned_van ?: 'unassigned')) : '—' }}</td>
                                <td class="p-4">{{ $item->dekkhotell ? __('service_requests.yes') : __('service_requests.no') }}</td>
                                <td class="p-4"><span class="et-admin-status et-admin-status--{{ $statusTone[$item->status->value] ?? 'neutral' }}">{{ __('service_requests.status_'.$item->status->value) }}</span></td>
                                <td class="p-4 text-easytire-muted">{{ __('service_requests.source_'.$item->source->value) }}<br>{{ $item->createdBy?->name }}</td>
                                <td class="p-4 text-easytire-muted">{{ $item->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $requests->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
