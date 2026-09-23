<x-layouts.admin :title="__('operations_day.title')">
    @php
        $statusTone = ['new' => 'attention', 'contacted' => 'info', 'closed' => 'success', 'cancelled' => 'neutral'];
        $createParameters = ['return_to' => $returnTo];
    @endphp
    <div class="min-w-0 space-y-8" data-operations-day>
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <a class="inline-flex min-h-11 items-center underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.index') }}">{{ __('operations_day.back') }}</a>
                <p class="mt-5 text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ __('operations_day.title') }}</h1>
                <p class="mt-3 max-w-3xl leading-7 text-easytire-muted">{{ __('operations_day.intro', ['timezone' => $timezone]) }}</p>
            </div>
            <a class="et-btn et-btn--primary" href="{{ route('admin.service-requests.create', $createParameters) }}">{{ __('service_requests.create') }}</a>
        </header>

        <form class="easytire-card grid min-w-0 gap-4 rounded-2xl p-5 sm:grid-cols-[minmax(0,14rem)_minmax(0,18rem)_auto] sm:items-end" method="get" action="{{ route('admin.service-requests.day') }}" data-operations-day-filters>
            <label class="block min-w-0 text-sm font-bold">{{ __('operations_day.date') }}
                <input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" type="date" name="date" value="{{ $date }}" required>
            </label>
            <label class="block min-w-0 text-sm font-bold">{{ __('operations_day.resource') }}
                <select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="resource">
                    @foreach(['all', 'workshop', 'van_1', 'van_2', 'mobile_unassigned'] as $value)
                        <option value="{{ $value }}" @selected($resource === $value)>{{ __('operations_day.resource_'.$value) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="et-btn et-btn--primary w-full sm:w-auto" type="submit">{{ __('operations_day.show') }}</button>
            <label class="flex min-h-11 min-w-0 items-start gap-3 text-sm font-bold sm:col-span-full sm:items-center">
                <input class="mt-1 shrink-0 sm:mt-0" type="checkbox" name="include_history" value="1" @checked($includeHistory)>
                <span class="min-w-0 break-words">{{ __('operations_day.include_history') }}</span>
            </label>
        </form>

        <section class="easytire-card min-w-0 rounded-2xl p-5" data-operations-day-navigation>
            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                @php
                    $todayParameters = ['date' => $today, 'resource' => $resource];
                    if ($includeHistory) $todayParameters['include_history'] = 1;
                @endphp
                <a class="min-h-11 min-w-0 break-words font-black text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route('admin.service-requests.day', $todayParameters) }}" data-testid="operations-day-today">
                    {{ __('operations_day.today', ['timezone' => $timezone, 'date' => $today]) }}
                </a>
                <a class="inline-flex min-h-11 min-w-0 items-center break-words font-bold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.index', ['without_date' => 1]) }}" data-testid="operations-day-undated">
                    {{ __('operations_day.requests_without_date') }}
                </a>
            </div>
            <nav class="mt-4 hidden min-w-0 grid-cols-1 gap-2 sm:grid sm:grid-cols-2 lg:grid-cols-5" aria-label="{{ __('operations_day.resource_navigation') }}">
                @foreach(['all', 'workshop', 'van_1', 'van_2', 'mobile_unassigned'] as $value)
                    @php
                        $resourceParameters = ['date' => $date, 'resource' => $value];
                        if ($includeHistory) $resourceParameters['include_history'] = 1;
                    @endphp
                    <a class="et-btn et-btn--small w-full min-w-0 whitespace-normal break-words text-center {{ $resource === $value ? 'et-btn--primary' : 'et-btn--outline' }}" href="{{ route('admin.service-requests.day', $resourceParameters) }}" data-day-resource="{{ $value }}" @if($resource === $value) aria-current="page" @endif>
                        {{ __('operations_day.resource_'.$value) }}
                    </a>
                @endforeach
            </nav>
        </section>

        <div class="flex min-w-0 flex-wrap items-baseline justify-between gap-3">
            <div class="min-w-0">
                <h2 class="break-words text-2xl font-black">{{ \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->locale(app()->getLocale() === 'ru' ? 'ru' : 'nb')->translatedFormat('l j. F Y') }}</h2>
                <p class="mt-1 text-sm text-easytire-muted">{{ trans_choice('operations_day.count', $requests->count(), ['count' => $requests->count()]) }}</p>
            </div>
        </div>

        <section class="easytire-card min-w-0 rounded-2xl p-5 sm:p-6" aria-labelledby="operations-preparation-heading" data-preparation-summary>
            <h2 class="text-xl font-black" id="operations-preparation-heading">{{ __('operations_day.preparation_title') }}</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-easytire-muted">{{ __('operations_day.preparation_intro') }}</p>
            <div class="mt-5 grid min-w-0 gap-4 lg:grid-cols-2">
                @foreach(['confirmed', 'pending'] as $group)
                    @php
                        $summary = $preparation[$group];
                    @endphp
                    <section class="min-w-0 rounded-xl border border-easytire-border bg-easytire-surface p-4" data-preparation-group="{{ $group }}">
                        <div class="flex min-w-0 flex-wrap items-baseline justify-between gap-2">
                            <h3 class="font-black">{{ __('operations_day.preparation_'.$group) }}</h3>
                            <p class="text-sm text-easytire-muted">{{ trans_choice('operations_day.preparation_request_count', $summary['request_count'], ['count' => $summary['request_count']]) }}</p>
                        </div>
                        <p class="mt-1 text-sm font-bold">{{ trans_choice('operations_day.preparation_action_count', count($summary['actions']), ['count' => count($summary['actions'])]) }}</p>
                        @if($summary['actions'] === [])
                            <p class="mt-3 text-sm text-easytire-muted">{{ __('operations_day.preparation_empty') }}</p>
                        @else
                            <ul class="mt-3 min-w-0 space-y-3">
                                @foreach($summary['actions'] as $action)
                                    @php
                                        $actionRoute = route('admin.service-requests.show', ['request' => $action['request'], 'return_to' => $returnTo]);
                                    @endphp
                                    <li class="min-w-0 border-l-2 border-easytire-brand pl-3 text-sm" data-preparation-action>
                                        <a class="break-words font-black underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $actionRoute }}">{{ $action['request']->reference }}</a>
                                        <p class="mt-1 break-words">
                                            {{ __('service_requests.vehicle_number', ['number' => $action['vehicle_number']]) }}
                                            @if(filled($action['vehicle']['vehicle'] ?? null)) · {{ $action['vehicle']['vehicle'] }} @endif
                                            @if(filled($action['vehicle']['registration_number'] ?? null)) · {{ $action['vehicle']['registration_number'] }} @endif
                                        </p>
                                        <p class="mt-1 break-words font-bold text-easytire-brand">{{ __('operations_day.storage_action_'.$action['storage_plan']) }}</p>
                                        @if($action['storage_number'])
                                            <p class="mt-1 break-words"><strong>{{ __('service_requests.form.storage_number') }}:</strong> {{ $action['storage_number'] }}</p>
                                        @elseif($action['needs_storage_number'])
                                            <p class="mt-1 break-words font-black text-amber-800" data-storage-number-warning>{{ __('operations_day.storage_number_missing') }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach
            </div>
        </section>

        @if($requests->isEmpty())
            <div class="rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center">
                <p class="text-easytire-muted">{{ __('operations_day.empty') }}</p>
            </div>
        @else
            <div class="min-w-0 space-y-4">
                @foreach($requests as $item)
                    @php
                        $start = $item->booking_starts_at ?: $item->preferred_period_starts_at;
                        $end = $item->booking_starts_at ? $item->booking_ends_at : $item->preferred_period_ends_at;
                        $timeKind = $item->booking_starts_at ? 'confirmed' : ($item->preferred_period_starts_at ? 'desired' : 'unconfirmed');
                        $vehicles = is_array($item->vehicles) ? array_values($item->vehicles) : [];
                        if ($vehicles === [] && (filled($item->vehicle) || filled($item->registration_number) || filled($item->tire_size) || $item->dekkhotell || $item->dekkhotell_already_stored !== null || filled($item->dekkhotell_number))) {
                            $hasGeneralStorageSignal = (bool) $item->dekkhotell
                                || $item->dekkhotell_already_stored !== null
                                || filled($item->dekkhotell_number);
                            $vehicles = [[
                                'vehicle' => $item->vehicle,
                                'registration_number' => $item->registration_number,
                                'tire_size' => $item->tire_size,
                                'storage_plan' => $hasGeneralStorageSignal
                                    ? \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::generalPlan((bool) $item->dekkhotell, $item->dekkhotell_already_stored)
                                    : 'none',
                                'storage_number' => $item->dekkhotell_number,
                            ]];
                        }
                        $mapUrl = $item->fulfilment === 'mobile' ? $maps->forAddress($item->address) : null;
                        $isHistorical = in_array($item->status, [\App\Modules\ServiceBooking\Enums\ServiceRequestStatus::Closed, \App\Modules\ServiceBooking\Enums\ServiceRequestStatus::Cancelled], true);
                        $itemRoute = route('admin.service-requests.show', ['request' => $item, 'return_to' => $returnTo]);
                    @endphp
                    <article class="easytire-card min-w-0 overflow-hidden rounded-2xl {{ $isHistorical ? 'opacity-70' : '' }}" data-operations-day-item data-status="{{ $item->status->value }}">
                        <div class="grid min-w-0 gap-5 p-5 sm:p-6 lg:grid-cols-[10rem_minmax(0,1.1fr)_minmax(17rem,.9fr)]">
                            <div class="min-w-0">
                                <p class="break-words text-3xl font-black tabular-nums">
                                    @if($start)
                                        {{ $start->setTimezone($timezone)->format('H:i') }}@if($end)–{{ $end->setTimezone($timezone)->format('H:i') }}@endif
                                    @else
                                        {{ __('operations_day.no_time') }}
                                    @endif
                                </p>
                                <p class="mt-1 break-words text-sm font-bold text-easytire-muted">{{ __('operations_day.time_'.$timeKind) }}</p>
                                @if(!$start && filled($item->preferred_window))<p class="mt-2 break-words text-sm">{{ $item->preferred_window }}</p>@endif
                                <span class="et-admin-status et-admin-status--{{ $statusTone[$item->status->value] ?? 'neutral' }} mt-4">{{ __('service_requests.status_'.$item->status->value) }}</span>
                                @if($isHistorical)<p class="mt-3 break-words text-sm font-bold text-easytire-muted" data-history-no-actions>{{ __('operations_day.history_no_actions') }}</p>@endif
                            </div>

                            <div class="min-w-0">
                                <a class="break-words text-lg font-black underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $itemRoute }}">{{ $item->reference }}</a>
                                <p class="mt-2 break-words font-bold">{{ $serviceTitles[$item->service_slug] ?? $item->service_slug }}</p>
                                <p class="mt-1 break-words text-sm text-easytire-muted">{{ __('service_requests.fulfilment_'.$item->fulfilment) }}@if($item->fulfilment === 'mobile') · {{ __('service_requests.assigned_van_'.($item->assigned_van ?: 'unassigned')) }}@endif</p>

                                <div class="mt-5 min-w-0 border-t border-easytire-border pt-4">
                                    <p class="break-words font-black">@if($item->client)<a class="underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.clients.show', $item->client) }}">{{ $item->name }}</a>@else{{ $item->name }}@endif</p>
                                    <a class="mt-1 inline-flex min-h-11 max-w-full items-center break-all font-bold text-easytire-brand underline decoration-2 underline-offset-4" href="tel:{{ preg_replace('/[^+0-9]/', '', $item->phone) }}">{{ $item->phone }}</a>
                                    @if(filled($item->address) || filled($item->district))<p class="mt-1 break-words text-sm text-easytire-muted">{{ collect([$item->address, $item->district])->filter()->join(' · ') }}</p>@endif
                                    @if($mapUrl)<a class="mt-3 inline-flex min-h-11 max-w-full items-center break-words font-bold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer">{{ __('operations_day.open_map') }}</a>@endif
                                </div>
                            </div>

                            <div class="min-w-0">
                                <h3 class="font-black">{{ __('operations_day.vehicles') }}</h3>
                                @if($vehicles === [])
                                    <p class="mt-3 text-sm text-easytire-muted">{{ __('operations_day.no_vehicles') }}</p>
                                @else
                                    <ol class="mt-3 min-w-0 space-y-3">
                                        @foreach($vehicles as $index => $vehicle)
                                            @php
                                                $storagePlan = in_array(($vehicle['storage_plan'] ?? 'unknown'), ['none', 'new', 'renew', 'last', 'unknown'], true)
                                                    ? ($vehicle['storage_plan'] ?? 'unknown')
                                                    : 'unknown';
                                                $needsStorageNumber = \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::requiresStoredSet($storagePlan)
                                                    && blank($vehicle['storage_number'] ?? null);
                                            @endphp
                                            <li class="min-w-0 border-l-2 border-easytire-brand pl-3" data-day-vehicle>
                                                <p class="break-words font-bold">{{ __('service_requests.vehicle_number', ['number' => $index + 1]) }}@if(filled($vehicle['vehicle'] ?? null)) · {{ $vehicle['vehicle'] }}@endif</p>
                                                <p class="mt-1 break-words text-sm text-easytire-muted">{{ collect([$vehicle['registration_number'] ?? null, $vehicle['tire_size'] ?? null])->filter()->join(' · ') ?: __('service_requests.not_provided') }}</p>
                                                @if($isHistorical)
                                                    <p class="mt-2 break-words text-sm text-easytire-muted">{{ __('operations_day.storage_history_'.$storagePlan) }}</p>
                                                    @if(filled($vehicle['storage_number'] ?? null))<p class="mt-1 break-words text-sm"><strong>{{ __('service_requests.form.storage_number') }}:</strong> {{ $vehicle['storage_number'] }}</p>@endif
                                                @else
                                                    <p class="mt-2 break-words text-sm font-bold text-easytire-brand">{{ __('operations_day.storage_action_'.$storagePlan) }}</p>
                                                    @if($needsStorageNumber)<p class="mt-1 break-words text-sm font-black text-amber-800" data-storage-number-warning>{{ __('operations_day.storage_number_missing') }}</p>@endif
                                                    @if($storagePlan !== 'none' || filled($vehicle['storage_number'] ?? null))
                                                        <p class="mt-2 break-words text-sm"><strong>{{ __('service_requests.form.storage_plan') }}:</strong> {{ __('service_requests.form.storage_'.$storagePlan) }}</p>
                                                        <p class="mt-1 break-words text-sm"><strong>{{ __('service_requests.form.storage_number') }}:</strong> {{ $vehicle['storage_number'] ?? __('service_requests.not_provided') }}</p>
                                                    @endif
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                        </div>
                        <div class="min-w-0 border-t border-easytire-border bg-easytire-surface px-5 py-3 text-right sm:px-6">
                            @if($item->canBeRescheduled())<a class="mr-4 inline-flex min-h-11 max-w-full items-center break-words text-left font-bold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route('admin.service-requests.reschedule.edit', ['serviceRequest' => $item, 'return_to' => $returnTo]) }}">{{ $item->booking_slot_key ? __('service_requests.reschedule') : __('service_requests.assign_exact_time') }}</a>@endif
                            <a class="inline-flex min-h-11 max-w-full items-center break-words text-left font-bold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $itemRoute }}">{{ __('operations_day.open_request') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.admin>
