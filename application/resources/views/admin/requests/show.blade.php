<x-layouts.admin :title="__('service_requests.details')">
    @php
        $editParameters = ['request' => $request];
        if (filled($returnTo ?? null)) $editParameters['return_to'] = $returnTo;
        $backHref = filled($returnTo ?? null) ? $returnTo : route('admin.service-requests.index');
    @endphp
    <div class="max-w-6xl space-y-8">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div><a class="inline-flex min-h-11 items-center underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $backHref }}" data-testid="request-back">{{ filled($returnTo ?? null) ? __('operations_day.back_to_day') : __('service_requests.back') }}</a><p class="mt-5 text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">{{ __('service_requests.reference') }}</p><h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ $request->reference }}</h1></div>
            <a class="et-btn et-btn--primary" href="{{ route('admin.service-requests.edit', $editParameters) }}" data-testid="request-edit">{{ __('service_requests.edit') }}</a>
        </header>
        @if (session('status'))<p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="easytire-card rounded-2xl p-6"><h2 class="text-lg font-black">{{ __('service_requests.customer') }}</h2><dl class="mt-5 space-y-4 text-sm"><div><dt class="text-easytire-muted">{{ __('service_requests.contact') }}</dt><dd class="mt-1 font-bold">{{ $request->name }}</dd><dd>{{ $request->email ?: __('service_requests.no_email') }}</dd><dd><a class="underline" href="tel:{{ $request->phone }}">{{ $request->phone }}</a></dd>@if($request->client)<dd class="mt-2"><a class="font-bold text-easytire-brand underline" href="{{ route('admin.clients.show', $request->client) }}">{{ __('service_requests.open_client') }}</a></dd>@else<dd class="mt-2"><a class="font-bold text-easytire-brand underline" href="{{ route('admin.clients.create', ['service_request' => $request->id]) }}">{{ __('service_requests.create_and_link_client') }}</a></dd>@endif</div><div><dt class="text-easytire-muted">{{ __('service_requests.address') }}</dt><dd class="mt-1">{{ $request->address ?: __('service_requests.not_provided') }}</dd>@if($request->district)<dd class="mt-1 text-easytire-muted">{{ __('service_requests.district') }}: {{ $request->district }}</dd>@endif @if($mapUrl)<dd class="mt-2"><a class="font-bold text-easytire-brand underline" href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer">{{ __('service_requests.open_map') }}</a></dd>@endif</div><div><dt class="text-easytire-muted">{{ __('service_requests.note') }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ $request->note ?: __('service_requests.not_provided') }}</dd></div></dl></section>
            <section class="easytire-card rounded-2xl p-6"><h2 class="text-lg font-black">{{ __('service_requests.follow_up') }}</h2><dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-easytire-muted">{{ __('service_requests.status') }}</dt><dd class="mt-1 font-bold">{{ __('service_requests.status_'.$request->status->value) }}</dd></div><div><dt class="text-easytire-muted">{{ __('service_requests.source') }}</dt><dd class="mt-1 font-bold">{{ __('service_requests.source_'.$request->source->value) }}</dd></div><div><dt class="text-easytire-muted">{{ __('service_requests.created_by') }}</dt><dd class="mt-1">{{ $request->createdBy?->name ?: __('service_requests.public_customer') }}</dd></div><div><dt class="text-easytire-muted">{{ __('service_requests.received') }}</dt><dd class="mt-1">{{ $request->created_at->format('Y-m-d H:i') }}</dd></div><div class="sm:col-span-2"><dt class="text-easytire-muted">{{ __('service_requests.admin_note') }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ $request->admin_note ?: __('service_requests.not_provided') }}</dd></div></dl></section>
        </div>

        <section class="easytire-card rounded-2xl p-6"><h2 class="text-lg font-black">{{ __('service_requests.booking') }}</h2><dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3"><div><dt class="text-easytire-muted">{{ __('service_requests.service') }}</dt><dd class="mt-1 font-bold">{{ $serviceTitle }}</dd></div><div><dt class="text-easytire-muted">{{ __('service_requests.fulfilment') }}</dt><dd class="mt-1 font-bold">{{ __('service_requests.fulfilment_'.$request->fulfilment) }}</dd></div>@if($request->fulfilment === 'mobile')<div><dt class="text-easytire-muted">{{ __('service_requests.assigned_van') }}</dt><dd class="mt-1 font-bold">{{ __('service_requests.assigned_van_'.($request->assigned_van ?: 'unassigned')) }}</dd><p class="mt-1 text-easytire-muted">{{ __('service_requests.assigned_van_notice') }}</p></div>@endif<div><dt class="text-easytire-muted">{{ __('service_requests.preferred_date') }}</dt><dd class="mt-1">{{ $request->preferred_date?->format('Y-m-d') ?: __('service_requests.unconfirmed') }}</dd></div><div><dt class="text-easytire-muted">{{ __('service_requests.preferred_window') }}</dt><dd class="mt-1">{{ $request->preferred_window ?: __('service_requests.unconfirmed') }}</dd></div><div><dt class="text-easytire-muted">{{ __('service_requests.vehicle') }}</dt><dd class="mt-1">{{ $request->vehicle ?: __('service_requests.not_provided') }}</dd></div><div><dt class="text-easytire-muted">{{ __('service_requests.registration_number') }}</dt><dd class="mt-1">{{ $request->registration_number ?: __('service_requests.not_provided') }}</dd></div><div><dt class="text-easytire-muted">{{ __('service_requests.tire_size') }}</dt><dd class="mt-1">{{ $request->tire_size ?: __('service_requests.not_provided') }}</dd></div><div><dt class="text-easytire-muted">{{ __('service_requests.dekkhotell') }}</dt><dd class="mt-1">{{ $request->dekkhotell ? __('service_requests.yes') : __('service_requests.no') }}</dd></div>@if($request->quote_min_minor)<div><dt class="text-easytire-muted">{{ __('service_requests.calculated_quote') }}</dt><dd class="mt-1 font-bold">{{ number_format($request->quote_min_minor / 100, 0, ',', ' ') }}@if($request->quote_max_minor !== $request->quote_min_minor)–{{ number_format($request->quote_max_minor / 100, 0, ',', ' ') }}@endif {{ $request->quote_currency }}</dd></div>@endif</dl>
            @php
                $generalStoragePlan = \App\Modules\ServiceBooking\ServiceRequestVehiclePayload::generalPlan(
                    (bool) $request->dekkhotell,
                    $request->dekkhotell_already_stored,
                );
            @endphp
            @if(blank($request->vehicles) && ($request->dekkhotell || $request->dekkhotell_already_stored !== null || filled($request->dekkhotell_number)))
                <div class="mt-5 grid gap-3 border-t border-easytire-border pt-5 text-sm sm:grid-cols-2" data-testid="general-storage-already">
                    <div><span class="text-easytire-muted">{{ __('service_requests.form.storage_plan') }}:</span> <strong>{{ __('service_requests.form.storage_'.$generalStoragePlan) }}</strong></div>
                    <div><span class="text-easytire-muted">{{ __('service_requests.form.storage_number') }}:</span> <strong>{{ $request->dekkhotell_number ?: __('service_requests.not_provided') }}</strong></div>
                </div>
            @endif
            @if($request->service_variant)
                @php
                    $variantKey = 'project.request.'.$request->service_variant;
                @endphp
                <div class="mt-5 text-sm"><span class="text-easytire-muted">{{ __('service_requests.service_variant') }}:</span> <strong>{{ Illuminate\Support\Facades\Lang::has($variantKey) ? __($variantKey) : Illuminate\Support\Str::headline($request->service_variant) }}</strong></div>
            @endif
            @if($request->additional_services)
                <div class="mt-3 text-sm"><span class="text-easytire-muted">{{ __('service_requests.additional_services') }}:</span> <strong>{{ collect($request->additional_services)->map(function ($item) { $key = 'project.request.'.$item; return Illuminate\Support\Facades\Lang::has($key) ? __($key) : Illuminate\Support\Str::headline($item); })->join(', ') }}</strong></div>
            @endif
            @if($request->booking_starts_at)
                <div class="mt-6 border-l-4 border-easytire-brand pl-4" data-testid="request-booked-slot"><strong>{{ __('service_requests.exact_slot') }}</strong><p class="mt-1">{{ $request->booking_starts_at->setTimezone(config('service_booking.timezone'))->format('Y-m-d H:i') }}–{{ $request->booking_ends_at->setTimezone(config('service_booking.timezone'))->format('H:i') }}</p></div>
            @else
                <div class="mt-6 border-l-4 border-amber-400 pl-4"><strong>{{ __('service_requests.unconfirmed') }}</strong><p class="mt-1 text-sm text-easytire-muted">{{ __('service_requests.unconfirmed_intro') }}</p></div>
            @endif
            @if($request->vehicles)
                <div class="mt-6 border-t border-easytire-border pt-5">
                    <h3 class="font-black">{{ __('service_requests.vehicles') }}</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach($request->vehicles as $index => $vehicle)
                            @php
                                $vehicleType = $vehicle['vehicle_type'] ?? 'passenger';
                                $rimBand = $vehicle['rim_band'] ?? 'unknown';
                                $storagePlan = $vehicle['storage_plan'] ?? 'none';
                            @endphp
                            <div class="border-l-2 border-easytire-brand pl-3 text-sm" data-request-vehicle>
                                <strong>{{ __('service_requests.vehicle_number', ['number' => $index + 1]) }}: {{ $vehicle['vehicle'] ?? __('service_requests.not_provided') }}</strong><br>
                                {{ ($vehicle['registration_number'] ?? null) ?: __('service_requests.not_provided') }} · {{ ($vehicle['tire_size'] ?? null) ?: __('service_requests.not_provided') }}<br>
                                {{ __('service_requests.form.'.$vehicleType) }} · {{ __('service_requests.form.rim_'.$rimBand) }}<br>
                                {{ __('service_requests.form.storage_'.$storagePlan) }}<br>
                                <span class="text-easytire-muted">{{ __('service_requests.form.storage_number') }}:</span> {{ ($vehicle['storage_number'] ?? null) ?: __('service_requests.not_provided') }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-layouts.admin>
