<x-layouts.admin :title="__('service_requests.title')">
    <div class="space-y-8">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('service_requests.title') }}</h1>
                <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('service_requests.intro') }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a class="et-btn et-btn--outline" href="{{ route('admin.service-requests.day') }}">{{ __('service_requests.operations_day') }}</a>
                <a class="et-btn et-btn--primary" href="{{ route('admin.service-requests.create') }}" data-testid="request-create">{{ __('service_requests.create') }}</a>
            </div>
        </header>

        <nav class="flex flex-wrap gap-2" aria-label="{{ __('service_requests.views') }}" data-testid="request-views">
            @foreach(['all', 'new', 'workshop', 'mobile', 'additional'] as $view)
                <a class="et-btn {{ $activeView === $view ? 'et-btn--primary' : 'et-btn--outline' }}" href="{{ route('admin.service-requests.index', $view === 'all' ? [] : ['view' => $view]) }}" @if($activeView === $view) aria-current="page" @endif>{{ __('service_requests.view_'.$view) }}</a>
            @endforeach
        </nav>

        <section class="easytire-card rounded-2xl p-5" aria-labelledby="request-queues-heading" data-testid="request-queues">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="text-lg font-black" id="request-queues-heading">{{ __('service_requests.queues') }}</h2>
                <p class="text-sm text-easytire-muted">{{ __('service_requests.queues_overlap_notice') }}</p>
            </div>
            <nav class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="{{ __('service_requests.queues') }}">
                @foreach(['new', 'pending_time', 'unassigned_van', 'missing_kit', 'today'] as $queue)
                    <a class="et-admin-queue {{ ($filters['queue'] ?? null) === $queue ? 'is-active' : '' }}" href="{{ route('admin.service-requests.index', ['queue' => $queue]) }}" @if(($filters['queue'] ?? null) === $queue) aria-current="page" @endif>
                        <span>{{ __('service_requests.queue_'.$queue) }}</span><strong>{{ $queueCounts[$queue] }}</strong>
                    </a>
                @endforeach
            </nav>
        </section>

        <form class="easytire-card grid gap-4 rounded-2xl p-5 md:grid-cols-2 xl:grid-cols-4" method="get" action="{{ route('admin.service-requests.index') }}" data-testid="request-filters">
            @if($activeView !== 'all')<input type="hidden" name="view" value="{{ $activeView }}">@endif
            @if(filled($filters['queue'] ?? null))<input type="hidden" name="queue" value="{{ $filters['queue'] }}">@endif
            <label class="block text-sm font-bold md:col-span-2">{{ __('service_requests.search') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('service_requests.search_hint') }}"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.date_from') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="date_from" value="{{ $filters['date_from'] ?? ($filters['date'] ?? '') }}" type="date"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.date_to') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="date_to" value="{{ $filters['date_to'] ?? ($filters['date'] ?? '') }}" type="date"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.service') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="service"><option value="">{{ __('service_requests.all') }}</option>@foreach($serviceOptions as $service)<option value="{{ $service->slug }}" @selected(($filters['service'] ?? '') === $service->slug)>{{ app()->getLocale() === 'ru' && filled($service->title_ru) ? $service->title_ru : $service->title_nb }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.fulfilment') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="fulfilment"><option value="">{{ __('service_requests.all') }}</option>@foreach(['workshop','mobile'] as $value)<option value="{{ $value }}" @selected(($filters['fulfilment'] ?? '') === $value)>{{ __('service_requests.fulfilment_'.$value) }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.status') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="status"><option value="">{{ __('service_requests.all') }}</option>@foreach(['new','contacted','closed','cancelled'] as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __('service_requests.status_'.$value) }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.district') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="district" value="{{ $filters['district'] ?? '' }}"></label>
            <label class="block text-sm font-bold">{{ __('service_requests.dekkhotell') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="dekkhotell"><option value="">{{ __('service_requests.all') }}</option><option value="1" @selected(($filters['dekkhotell'] ?? '') === '1')>{{ __('service_requests.yes') }}</option><option value="0" @selected(($filters['dekkhotell'] ?? '') === '0')>{{ __('service_requests.no') }}</option></select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.storage_operation') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="storage_operation"><option value="">{{ __('service_requests.all') }}</option>@foreach(['none','new','renew','last','unknown'] as $value)<option value="{{ $value }}" @selected(($filters['storage_operation'] ?? '') === $value)>{{ __('service_requests.storage_operation_'.$value) }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('service_requests.assigned_van') }}<select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="assigned_van"><option value="">{{ __('service_requests.all') }}</option>@foreach(['unassigned','van_1','van_2'] as $value)<option value="{{ $value }}" @selected(($filters['assigned_van'] ?? '') === $value)>{{ __('service_requests.assigned_van_'.$value) }}</option>@endforeach</select></label>
            <label class="flex min-h-11 items-center gap-3 text-sm font-bold md:col-span-2 xl:col-span-4"><input type="checkbox" name="without_date" value="1" @checked(request()->boolean('without_date'))><span>{{ __('service_requests.without_date') }}</span></label>
            <div class="flex flex-wrap items-end gap-3 md:col-span-2 xl:col-span-2">
                <button class="et-btn et-btn--primary" type="submit">{{ __('service_requests.apply_filters') }}</button>
                <a class="et-btn et-btn--outline" href="{{ route('admin.service-requests.index', $activeView === 'all' ? [] : ['view' => $activeView]) }}">{{ __('service_requests.reset_filters') }}</a>
            </div>
        </form>

        @php
            $requestTimeSummary = static function ($item): string {
                $timezone = config('service_booking.timezone');
                if ($item->booking_starts_at) {
                    return __('service_requests.time_confirmed', [
                        'time' => $item->booking_starts_at->setTimezone($timezone)->format('Y-m-d H:i'),
                    ]);
                }
                if ($item->preferred_period_starts_at && $item->preferred_period_ends_at) {
                    return __('service_requests.time_preferred_period', [
                        'period' => $item->preferred_period_starts_at->setTimezone($timezone)->format('Y-m-d H:i')
                            .'–'.$item->preferred_period_ends_at->setTimezone($timezone)->format('H:i'),
                    ]);
                }
                if ($item->preferred_date) {
                    return __('service_requests.time_desired', [
                        'date' => $item->preferred_date->format('Y-m-d'),
                        'window' => $item->preferred_window ?: __('service_requests.unconfirmed'),
                    ]);
                }

                return __('service_requests.time_not_agreed');
            };
        @endphp

        @if($requests->isEmpty())
            <div class="rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center"><p class="text-easytire-muted">{{ __('service_requests.empty_view') }}</p><a class="mt-4 inline-flex font-bold text-easytire-brand underline" href="{{ route('admin.service-requests.index') }}">{{ __('service_requests.show_all') }}</a></div>
        @else
            @php
                $statusTone = ['new' => 'attention', 'contacted' => 'info', 'closed' => 'success', 'cancelled' => 'neutral'];
            @endphp
            <div class="space-y-3 2xl:hidden">
                @foreach($requests as $item)
                    @php
                        $openParameters = request()->except('selected');
                        $openParameters['selected'] = $item->id;
                        $openUrl = route('admin.service-requests.index', $openParameters);
                        $operationalVehicles = $item->operationalVehicles();
                        $storageOperations = collect($operationalVehicles)->map(function ($vehicle) {
                            $plan = (string) ($vehicle['storage_plan'] ?? 'unknown');

                            return __('service_requests.storage_operation_'.$plan).(filled($vehicle['storage_number'] ?? null) ? ' · '.$vehicle['storage_number'] : '');
                        });
                    @endphp
                    <article class="easytire-card rounded-2xl p-5">
                        <div class="flex items-start justify-between gap-3"><a class="font-black underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $openUrl }}" data-request-open="{{ $item->id }}">{{ $item->reference }}</a><span class="et-admin-status et-admin-status--{{ $statusTone[$item->status->value] ?? 'neutral' }}">{{ __('service_requests.status_'.$item->status->value) }}</span></div>
                        <p class="mt-3 font-bold">@if($item->client)<a class="underline" href="{{ route('admin.clients.show', $item->client) }}">{{ $item->name }}</a>@else{{ $item->name }}@endif</p>
                        <p class="mt-1 text-sm text-easytire-muted">{{ $item->phone }} · {{ $serviceTitles[$item->service_slug] ?? $item->service_slug }}</p>
                        <p class="mt-3 text-sm">{{ __('service_requests.fulfilment_'.$item->fulfilment) }}@if($item->district) · {{ $item->district }}@endif</p>
                        <p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.vehicle_count', ['count' => count($operationalVehicles)]) }}</p>
                        <p class="mt-2 break-words text-sm font-bold text-easytire-brand">{{ $storageOperations->filter()->join(' · ') ?: __('service_requests.storage_operation_none') }}</p>
                        @if($item->search_match_reason)<p class="mt-2 text-xs font-bold text-easytire-muted" data-search-match>{{ __('service_requests.match_'.$item->search_match_reason['field'], ['value' => $item->search_match_reason['value'], 'vehicle' => $item->search_match_reason['vehicle'] ?? 1]) }}</p>@endif
                        @if($item->fulfilment === 'mobile')<p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.assigned_van') }}: {{ __('service_requests.assigned_van_'.($item->assigned_van ?: 'unassigned')) }}</p>@endif
                        <p class="mt-3 text-sm text-easytire-muted">{{ $requestTimeSummary($item) }}</p>
                        <p class="mt-2 text-xs text-easytire-muted">{{ __('service_requests.source_'.$item->source->value) }}@if($item->createdBy) · {{ $item->createdBy->name }}@endif · {{ $item->created_at->format('Y-m-d H:i') }}</p>
                    </article>
                @endforeach
            </div>
            <div class="easytire-card hidden overflow-x-auto rounded-2xl 2xl:block">
                <table class="min-w-[78rem] text-left text-sm">
                    <thead class="bg-easytire-ink text-white"><tr><th class="p-4">{{ __('service_requests.reference') }}</th><th class="p-4">{{ __('service_requests.customer') }}</th><th class="p-4">{{ __('service_requests.service') }}</th><th class="p-4">{{ __('service_requests.preferred_date') }}</th><th class="p-4">{{ __('service_requests.district') }}</th><th class="p-4">{{ __('service_requests.assigned_van') }}</th><th class="p-4">{{ __('service_requests.dekkhotell') }}</th><th class="p-4">{{ __('service_requests.status') }}</th><th class="p-4">{{ __('service_requests.source') }}</th><th class="p-4">{{ __('service_requests.received') }}</th></tr></thead>
                    <tbody class="divide-y divide-easytire-border">
                        @foreach($requests as $item)
                            @php
                                $openParameters = request()->except('selected');
                                $openParameters['selected'] = $item->id;
                                $openUrl = route('admin.service-requests.index', $openParameters);
                                $operationalVehicles = $item->operationalVehicles();
                                $storageOperations = collect($operationalVehicles)->map(function ($vehicle) {
                                    $plan = (string) ($vehicle['storage_plan'] ?? 'unknown');

                                    return __('service_requests.storage_operation_'.$plan).(filled($vehicle['storage_number'] ?? null) ? ' · '.$vehicle['storage_number'] : '');
                                });
                            @endphp
                            <tr class="transition hover:bg-easytire-brand-soft/50">
                                <td class="p-4 font-bold"><a class="underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $openUrl }}" data-request-open="{{ $item->id }}">{{ $item->reference }}</a>@if($item->search_match_reason)<span class="mt-2 block text-xs font-normal text-easytire-muted" data-search-match>{{ __('service_requests.match_'.$item->search_match_reason['field'], ['value' => $item->search_match_reason['value'], 'vehicle' => $item->search_match_reason['vehicle'] ?? 1]) }}</span>@endif</td>
                                <td class="p-4"><p class="font-bold">@if($item->client)<a class="underline" href="{{ route('admin.clients.show', $item->client) }}">{{ $item->name }}</a>@else{{ $item->name }}@endif</p><p class="mt-1 text-easytire-muted">{{ $item->phone }}</p></td>
                                <td class="p-4"><p>{{ $serviceTitles[$item->service_slug] ?? $item->service_slug }}</p><p class="mt-1 text-easytire-muted">{{ __('service_requests.fulfilment_'.$item->fulfilment) }}</p><p class="mt-1 text-easytire-muted">{{ __('service_requests.vehicle_count', ['count' => count($operationalVehicles)]) }}</p></td>
                                <td class="p-4">{{ $requestTimeSummary($item) }}</td>
                                <td class="p-4 text-easytire-muted">{{ $item->district ?: '—' }}</td>
                                <td class="p-4 text-easytire-muted">{{ $item->fulfilment === 'mobile' ? __('service_requests.assigned_van_'.($item->assigned_van ?: 'unassigned')) : '—' }}</td>
                                <td class="p-4"><span class="break-words text-xs font-bold">{{ $storageOperations->filter()->join(' · ') ?: __('service_requests.storage_operation_none') }}</span></td>
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

    @if($selectedRequest)
        @include('admin.requests.partials.workspace-drawer', [
            'request' => $selectedRequest,
            'returnTo' => $listReturnTo,
            'closeUrl' => $closeSelectedUrl,
            'inCurrentResults' => $selectedInResults,
            'mapUrl' => $selectedMapUrl,
            'serviceTitle' => $serviceTitles[$selectedRequest->service_slug] ?? $selectedRequest->service_slug,
        ])
    @endif
</x-layouts.admin>
