<x-layouts.admin :title="__('operations_day.title')">
    @php
        $statusTone = ['new' => 'attention', 'contacted' => 'info', 'closed' => 'success'];
    @endphp
    <div class="space-y-8" data-operations-day>
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a class="inline-flex min-h-11 items-center underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.index') }}">{{ __('operations_day.back') }}</a>
                <p class="mt-5 text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ __('operations_day.title') }}</h1>
                <p class="mt-3 max-w-3xl leading-7 text-easytire-muted">{{ __('operations_day.intro', ['timezone' => $timezone]) }}</p>
            </div>
            <a class="et-btn et-btn--primary" href="{{ route('admin.service-requests.create') }}">{{ __('service_requests.create') }}</a>
        </header>

        <form class="easytire-card grid gap-4 rounded-2xl p-5 sm:grid-cols-[minmax(0,14rem)_minmax(0,18rem)_auto] sm:items-end" method="get" action="{{ route('admin.service-requests.day') }}" data-operations-day-filters>
            <label class="block text-sm font-bold">{{ __('operations_day.date') }}
                <input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" type="date" name="date" value="{{ $date }}" required>
            </label>
            <label class="block text-sm font-bold">{{ __('operations_day.resource') }}
                <select class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="resource">
                    @foreach(['all', 'workshop', 'van_1', 'van_2', 'mobile_unassigned'] as $value)
                        <option value="{{ $value }}" @selected($resource === $value)>{{ __('operations_day.resource_'.$value) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="et-btn et-btn--primary w-full sm:w-auto" type="submit">{{ __('operations_day.show') }}</button>
            <label class="flex min-h-11 items-center gap-3 text-sm font-bold sm:col-span-full">
                <input type="checkbox" name="include_cancelled" value="1" @checked(($filters['include_cancelled'] ?? false) == true)>
                <span>{{ __('operations_day.include_cancelled') }}</span>
            </label>
        </form>

        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <div>
                <h2 class="text-2xl font-black">{{ \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->locale(app()->getLocale() === 'ru' ? 'ru' : 'nb')->translatedFormat('l j. F Y') }}</h2>
                <p class="mt-1 text-sm text-easytire-muted">{{ trans_choice('operations_day.count', $requests->count(), ['count' => $requests->count()]) }}</p>
            </div>
        </div>

        @if($requests->isEmpty())
            <div class="rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center">
                <p class="text-easytire-muted">{{ __('operations_day.empty') }}</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($requests as $item)
                    @php
                        $start = $item->booking_starts_at ?: $item->preferred_period_starts_at;
                        $end = $item->booking_starts_at ? $item->booking_ends_at : $item->preferred_period_ends_at;
                        $timeKind = $item->booking_starts_at ? 'confirmed' : ($item->preferred_period_starts_at ? 'desired' : 'unconfirmed');
                        $vehicles = is_array($item->vehicles) ? array_values($item->vehicles) : [];
                        if ($vehicles === [] && (filled($item->vehicle) || filled($item->registration_number) || filled($item->tire_size) || $item->dekkhotell || $item->dekkhotell_already_stored !== null || filled($item->dekkhotell_number))) {
                            $vehicles = [[
                                'vehicle' => $item->vehicle,
                                'registration_number' => $item->registration_number,
                                'tire_size' => $item->tire_size,
                                'storage_plan' => \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::generalPlan((bool) $item->dekkhotell, $item->dekkhotell_already_stored),
                                'storage_number' => $item->dekkhotell_number,
                            ]];
                        }
                        $mapUrl = $item->fulfilment === 'mobile' ? $maps->forAddress($item->address) : null;
                    @endphp
                    <article class="easytire-card overflow-hidden rounded-2xl {{ $item->status === \App\Modules\ServiceBooking\Enums\ServiceRequestStatus::Cancelled ? 'opacity-70' : '' }}" data-operations-day-item>
                        <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[10rem_minmax(0,1.1fr)_minmax(17rem,.9fr)]">
                            <div>
                                <p class="text-3xl font-black tabular-nums">
                                    @if($start)
                                        {{ $start->setTimezone($timezone)->format('H:i') }}@if($end)–{{ $end->setTimezone($timezone)->format('H:i') }}@endif
                                    @else
                                        {{ __('operations_day.no_time') }}
                                    @endif
                                </p>
                                <p class="mt-1 text-sm font-bold text-easytire-muted">{{ __('operations_day.time_'.$timeKind) }}</p>
                                @if(!$start && filled($item->preferred_window))<p class="mt-2 break-words text-sm">{{ $item->preferred_window }}</p>@endif
                                <span class="et-admin-status et-admin-status--{{ $statusTone[$item->status->value] ?? 'neutral' }} mt-4">{{ __('service_requests.status_'.$item->status->value) }}</span>
                            </div>

                            <div class="min-w-0">
                                <a class="break-words text-lg font-black underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.show', $item) }}">{{ $item->reference }}</a>
                                <p class="mt-2 font-bold">{{ $serviceTitles[$item->service_slug] ?? $item->service_slug }}</p>
                                <p class="mt-1 text-sm text-easytire-muted">{{ __('service_requests.fulfilment_'.$item->fulfilment) }}@if($item->fulfilment === 'mobile') · {{ __('service_requests.assigned_van_'.($item->assigned_van ?: 'unassigned')) }}@endif</p>

                                <div class="mt-5 border-t border-easytire-border pt-4">
                                    <p class="break-words font-black">@if($item->client)<a class="underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.clients.show', $item->client) }}">{{ $item->name }}</a>@else{{ $item->name }}@endif</p>
                                    <a class="mt-1 inline-flex min-h-11 items-center break-all font-bold text-easytire-brand underline decoration-2 underline-offset-4" href="tel:{{ preg_replace('/[^+0-9]/', '', $item->phone) }}">{{ $item->phone }}</a>
                                    @if(filled($item->address) || filled($item->district))<p class="mt-1 break-words text-sm text-easytire-muted">{{ collect([$item->address, $item->district])->filter()->join(' · ') }}</p>@endif
                                    @if($mapUrl)<a class="mt-3 inline-flex min-h-11 items-center font-bold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer">{{ __('operations_day.open_map') }}</a>@endif
                                </div>
                            </div>

                            <div class="min-w-0">
                                <h3 class="font-black">{{ __('operations_day.vehicles') }}</h3>
                                @if($vehicles === [])
                                    <p class="mt-3 text-sm text-easytire-muted">{{ __('operations_day.no_vehicles') }}</p>
                                @else
                                    <ol class="mt-3 space-y-3">
                                        @foreach($vehicles as $index => $vehicle)
                                            @php
                                                $storagePlan = in_array(($vehicle['storage_plan'] ?? 'unknown'), ['none', 'new', 'renew', 'last', 'unknown'], true)
                                                    ? ($vehicle['storage_plan'] ?? 'unknown')
                                                    : 'unknown';
                                            @endphp
                                            <li class="border-l-2 border-easytire-brand pl-3" data-day-vehicle>
                                                <p class="break-words font-bold">{{ __('service_requests.vehicle_number', ['number' => $index + 1]) }}@if(filled($vehicle['vehicle'] ?? null)) · {{ $vehicle['vehicle'] }}@endif</p>
                                                <p class="mt-1 break-words text-sm text-easytire-muted">{{ collect([$vehicle['registration_number'] ?? null, $vehicle['tire_size'] ?? null])->filter()->join(' · ') ?: __('service_requests.not_provided') }}</p>
                                                <p class="mt-2 break-words text-sm font-bold text-easytire-brand">{{ __('operations_day.storage_action_'.$storagePlan) }}</p>
                                                @if($storagePlan !== 'none' || filled($vehicle['storage_number'] ?? null))
                                                    <p class="mt-2 break-words text-sm"><strong>{{ __('service_requests.form.storage_plan') }}:</strong> {{ __('service_requests.form.storage_'.$storagePlan) }}</p>
                                                    <p class="mt-1 break-words text-sm"><strong>{{ __('service_requests.form.storage_number') }}:</strong> {{ $vehicle['storage_number'] ?? __('service_requests.not_provided') }}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                        </div>
                        <div class="border-t border-easytire-border bg-easytire-surface px-5 py-3 text-right sm:px-6">
                            <a class="inline-flex min-h-11 items-center font-bold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.show', $item) }}">{{ __('operations_day.open_request') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.admin>
