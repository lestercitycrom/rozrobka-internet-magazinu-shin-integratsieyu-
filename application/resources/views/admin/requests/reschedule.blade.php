<x-layouts.admin :title="$request->booking_slot_key ? __('service_requests.reschedule') : __('service_requests.assign_exact_time')">
    @php
        $backHref = $returnTo ?: route('admin.service-requests.show', $request);
        $currentStart = $request->booking_starts_at?->setTimezone($timezone);
        $currentEnd = $request->booking_ends_at?->setTimezone($timezone);
    @endphp
    <div class="max-w-4xl space-y-7" data-testid="request-reschedule-page">
        <header>
            <a class="inline-flex min-h-11 items-center font-bold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $backHref }}">{{ __('service_requests.reschedule_back') }}</a>
            <p class="mt-5 text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">{{ $request->reference }}</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ $request->booking_slot_key ? __('service_requests.reschedule') : __('service_requests.assign_exact_time') }}</h1>
            <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('service_requests.reschedule_intro', ['timezone' => $timezone]) }}</p>
        </header>

        @if($errors->any())
            <div class="border border-red-300 bg-red-50 p-4 text-sm text-red-900" role="alert" tabindex="-1" autofocus data-testid="reschedule-errors">
                <strong>{{ __('service_requests.validation_summary') }}</strong>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                @if($errors->has('expected_version'))
                    <a class="mt-3 inline-flex min-h-11 items-center font-bold underline" href="{{ route('admin.service-requests.reschedule.edit', array_filter(['serviceRequest' => $request, 'date' => old('booking_date', $date->format('Y-m-d')), 'return_to' => $returnTo])) }}">{{ __('service_requests.reschedule_reload') }}</a>
                @endif
            </div>
        @endif

        <section class="easytire-card border-l-4 border-easytire-brand p-5 sm:p-6" aria-labelledby="current-booking-heading">
            <h2 class="text-xl font-black" id="current-booking-heading">{{ __('service_requests.reschedule_current') }}</h2>
            @if($currentStart)
                <p class="mt-3 text-lg font-black" data-testid="reschedule-current-slot">{{ $currentStart->format('Y-m-d H:i') }}–{{ $currentEnd?->format('H:i') }}</p>
            @else
                <p class="mt-3 font-bold" data-testid="reschedule-current-preference">{{ $request->preferred_date?->format('Y-m-d') ?: __('service_requests.unconfirmed') }} · {{ $request->preferred_window ?: __('service_requests.unconfirmed') }}</p>
            @endif
            <p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.reschedule_preserves') }}</p>
        </section>

        <form class="easytire-card grid gap-4 p-5 sm:grid-cols-[minmax(0,18rem)_auto] sm:items-end sm:p-6" method="get" action="{{ route('admin.service-requests.reschedule.edit', $request) }}" data-testid="reschedule-date-form">
            @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
            <label class="block text-sm font-bold">{{ __('service_requests.reschedule_date') }}
                <input class="easytire-input mt-2 min-h-11 w-full px-3" type="date" name="date" min="{{ now($timezone)->format('Y-m-d') }}" value="{{ $date->format('Y-m-d') }}" required>
            </label>
            <button class="et-btn et-btn--outline w-full sm:w-auto" type="submit">{{ __('service_requests.reschedule_show_slots') }}</button>
        </form>

        <form class="easytire-card space-y-5 p-5 sm:p-6" method="post" action="{{ route('admin.service-requests.reschedule.update', $request) }}" data-dirty-guard data-testid="reschedule-form">
            @csrf
            <input type="hidden" name="booking_date" value="{{ $date->format('Y-m-d') }}">
            <input type="hidden" name="expected_version" value="{{ old('expected_version', $request->operation_version) }}">
            <input type="hidden" name="command_id" value="{{ $commandId }}">
            @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif

            <div>
                <label class="block text-sm font-bold" for="booking-slot-key">{{ __('service_requests.reschedule_new_slot') }}</label>
                @if(!$availability['configured'])
                    <p class="mt-2 border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status">{{ __('service_requests.reschedule_schedule_missing') }}</p>
                @elseif($availability['slots']->isEmpty())
                    <p class="mt-2 border border-easytire-border bg-easytire-surface p-4 text-sm text-easytire-muted" role="status">{{ __('service_requests.no_slots') }}</p>
                @else
                    <select class="easytire-input mt-2 min-h-11 w-full px-3" id="booking-slot-key" name="booking_slot_key" required aria-invalid="{{ $errors->has('booking_slot_key') ? 'true' : 'false' }}">
                        <option value="">{{ __('service_requests.select_slot') }}</option>
                        @foreach($availability['slots'] as $slot)
                            <option value="{{ $slot->key }}" @selected(old('booking_slot_key') === $slot->key)>{{ $slot->startsAt->setTimezone($timezone)->format('H:i') }}–{{ $slot->endsAt->setTimezone($timezone)->format('H:i') }} · {{ trans_choice('service_requests.reschedule_remaining', $slot->remaining, ['count' => $slot->remaining]) }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <label class="flex items-start gap-3 text-sm font-bold">
                <input class="mt-1 shrink-0" type="checkbox" name="confirm_reschedule" value="1" @checked(old('confirm_reschedule')) required>
                <span>{{ __('service_requests.reschedule_confirm') }}</span>
            </label>
            <p class="text-sm text-easytire-muted">{{ __('service_requests.reschedule_no_notifications') }}</p>
            <button class="et-btn et-btn--primary" type="submit" @disabled(!$availability['configured'] || $availability['slots']->isEmpty())>{{ $request->booking_slot_key ? __('service_requests.reschedule_submit') : __('service_requests.assign_exact_time_submit') }}</button>
        </form>
    </div>
</x-layouts.admin>
