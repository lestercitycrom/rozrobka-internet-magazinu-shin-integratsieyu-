@php
    $vehicles = $request->operationalVehicles();
    $statusTone = ['new' => 'attention', 'contacted' => 'info', 'closed' => 'success', 'cancelled' => 'neutral'];
    $editUrl = route('admin.service-requests.edit', ['request' => $request, 'return_to' => $returnTo]);
@endphp
<div class="et-admin-request-drawer" data-request-drawer data-close-url="{{ $closeUrl }}" data-unsaved-message="{{ __('service_requests.unsaved_close_confirm') }}">
    <a class="et-admin-request-drawer__scrim" href="{{ $closeUrl }}" aria-label="{{ __('service_requests.close_details') }}" data-request-drawer-close></a>
    <aside class="et-admin-request-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="request-drawer-title" tabindex="-1" data-request-drawer-panel>
        <header class="et-admin-request-drawer__head">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-easytire-brand">{{ __('service_requests.reference') }}</p>
                <h2 class="mt-1 break-words text-2xl font-black" id="request-drawer-title">{{ $request->reference }}</h2>
            </div>
            <a class="et-btn et-btn--outline et-btn--small" href="{{ $closeUrl }}" data-request-drawer-close>{{ __('service_requests.close_details') }}</a>
        </header>

        @if(!$inCurrentResults)
            <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950" role="status">{{ __('service_requests.outside_current_filter') }}</p>
        @endif
        @if($errors->any())
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-900" role="alert" data-testid="quick-action-errors">
                <strong>{{ __('service_requests.validation_summary') }}</strong>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if($errors->has('expected_version') || (filled(old('command')) && filled(old('expected_version')) && (int) old('expected_version') !== (int) $request->operation_version))
            <section class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950" role="status" data-testid="request-version-conflict">
                <p class="font-bold">{{ __('service_requests.latest_version_notice', ['version' => $request->operation_version, 'status' => __('service_requests.status_'.$request->status->value)]) }}</p>
                <p class="mt-2">{{ __('service_requests.conflict_current_values') }}</p>
                <dl class="mt-2 grid gap-2 sm:grid-cols-2" data-testid="request-current-values">
                    <div><dt class="font-bold">{{ __('service_requests.status') }}</dt><dd>{{ __('service_requests.status_'.$request->status->value) }}</dd></div>
                    <div><dt class="font-bold">{{ __('service_requests.assigned_van') }}</dt><dd>{{ $request->assigned_van ? __('service_requests.assigned_van_'.$request->assigned_van) : __('service_requests.assigned_van_unassigned') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="font-bold">{{ __('service_requests.admin_note') }}</dt><dd class="whitespace-pre-wrap">{{ filled($request->admin_note) ? $request->admin_note : '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="font-bold">{{ __('service_requests.linked_client') }}</dt><dd>{{ $request->client ? $request->client->name.' · #'.$request->client->id : __('service_requests.no_linked_client') }}</dd></div>
                    @foreach($vehicles as $index => $vehicle)
                        @if(in_array($vehicle['storage_plan'] ?? 'none', ['new', 'renew', 'last'], true))
                            <div><dt class="font-bold">{{ __('service_requests.storage_number_for_vehicle', ['number' => $index + 1]) }}</dt><dd>{{ filled($vehicle['storage_number'] ?? null) ? $vehicle['storage_number'] : '—' }}</dd></div>
                        @endif
                    @endforeach
                </dl>
            </section>
        @endif
        @if(session('status'))<p class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <span class="et-admin-status et-admin-status--{{ $statusTone[$request->status->value] ?? 'neutral' }}">{{ __('service_requests.status_'.$request->status->value) }}</span>
            @if($request->canBeRescheduled())<a class="et-btn et-btn--outline et-btn--small" href="{{ route('admin.service-requests.reschedule.edit', ['serviceRequest' => $request, 'return_to' => $returnTo]) }}" data-testid="request-reschedule">{{ $request->booking_slot_key ? __('service_requests.reschedule') : __('service_requests.assign_exact_time') }}</a>@endif
            <a class="et-btn et-btn--primary et-btn--small" href="{{ $editUrl }}" data-testid="request-edit">{{ __('service_requests.edit_full') }}</a>
            <a class="et-btn et-btn--outline et-btn--small" href="{{ route('admin.service-requests.show', ['request' => $request, 'return_to' => $returnTo]) }}">{{ __('service_requests.open_full_page') }}</a>
        </div>

        <section class="et-admin-request-summary">
            <h3>{{ __('service_requests.customer') }}</h3>
            <p class="font-black">{{ $request->name }}</p>
            <p><a class="font-bold underline" href="tel:{{ preg_replace('/[^+0-9]/', '', $request->phone) }}">{{ $request->phone }}</a>@if($request->email) · <a class="break-all underline" href="mailto:{{ $request->email }}">{{ $request->email }}</a>@endif</p>
            @if($request->client)<a class="mt-2 inline-flex font-bold text-easytire-brand underline" href="{{ route('admin.clients.show', $request->client) }}">{{ __('service_requests.open_client') }}</a>@else<a class="mt-2 inline-flex font-bold text-easytire-brand underline" href="{{ route('admin.clients.create', ['service_request' => $request->id, 'return_to' => $returnTo]) }}">{{ __('service_requests.create_and_link_client') }}</a>@endif
            @if($request->address)<p class="mt-3">{{ $request->address }}@if($request->district) · {{ $request->district }}@endif</p>@endif
            @if($mapUrl)<a class="mt-2 inline-flex font-bold text-easytire-brand underline" href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer">{{ __('service_requests.open_map') }}</a>@endif
            @if($request->note)<p class="mt-3 whitespace-pre-wrap text-sm"><span class="font-bold">{{ __('service_requests.note') }}:</span> {{ $request->note }}</p>@endif
        </section>

        <section class="et-admin-request-summary">
            <h3>{{ __('service_requests.booking') }}</h3>
            <p class="font-black">{{ $serviceTitle }} · {{ __('service_requests.fulfilment_'.$request->fulfilment) }}</p>
            <p class="mt-1">{{ $request->booking_starts_at?->setTimezone(config('service_booking.timezone'))->format('Y-m-d H:i') ?: ($request->preferred_date?->format('Y-m-d').' · '.($request->preferred_window ?: __('service_requests.unconfirmed'))) }}</p>
            @if($request->quote_min_minor)
                <p class="mt-2 text-sm"><span class="font-bold">{{ __('service_requests.calculated_quote') }}:</span> {{ number_format($request->quote_min_minor / 100, 0, ',', ' ') }}@if($request->quote_max_minor !== $request->quote_min_minor)–{{ number_format($request->quote_max_minor / 100, 0, ',', ' ') }}@endif {{ $request->quote_currency }}</p>
            @endif
            @if($request->service_variant)
                @php
                    $variantKey = 'project.request.'.$request->service_variant;
                @endphp
                <p class="mt-2 text-sm"><span class="font-bold">{{ __('service_requests.service_variant') }}:</span> {{ Illuminate\Support\Facades\Lang::has($variantKey) ? __($variantKey) : Illuminate\Support\Str::headline($request->service_variant) }}</p>
            @endif
            @if($request->additional_services)
                <p class="mt-2 text-sm"><span class="font-bold">{{ __('service_requests.additional_services') }}:</span> {{ collect($request->additional_services)->map(function ($item) { $key = 'project.request.'.$item; return Illuminate\Support\Facades\Lang::has($key) ? __($key) : Illuminate\Support\Str::headline($item); })->join(', ') }}</p>
            @endif
            <ol class="mt-3 space-y-3">
                @forelse($vehicles as $index => $vehicle)
                    <li class="border-l-2 border-easytire-brand pl-3 text-sm">
                        <strong>{{ __('service_requests.vehicle_number', ['number' => $index + 1]) }}@if(filled($vehicle['vehicle'] ?? null)) · {{ $vehicle['vehicle'] }}@endif</strong>
                        <p>{{ collect([$vehicle['registration_number'] ?? null, $vehicle['tire_size'] ?? null])->filter()->join(' · ') ?: __('service_requests.not_provided') }}</p>
                        <p class="mt-1 font-bold">{{ __('service_requests.storage_operation_'.($vehicle['storage_plan'] ?? 'unknown')) }}</p>
                        @if(filled($vehicle['storage_number'] ?? null))<p>{{ __('service_requests.form.storage_number') }}: {{ $vehicle['storage_number'] }}</p>@endif
                    </li>
                @empty
                    <li class="text-sm text-easytire-muted">{{ __('service_requests.not_provided') }}</li>
                @endforelse
            </ol>
        </section>

        @include('admin.requests.partials.quick-actions', ['request' => $request, 'returnTo' => $returnTo, 'vehicles' => $vehicles])
        @include('admin.requests.partials.history', ['request' => $request])
    </aside>
</div>
