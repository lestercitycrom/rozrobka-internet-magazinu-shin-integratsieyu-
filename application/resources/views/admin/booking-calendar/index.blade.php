<x-layouts.admin :title="__('booking_calendar.admin.title')">
    <div class="space-y-10" data-testid="booking-calendar-admin">
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('booking_calendar.admin.title') }}</h1>
            <p class="mt-3 max-w-3xl leading-7 text-easytire-muted">{{ __('booking_calendar.admin.intro', ['timezone' => $timezone]) }}</p>
        </header>

        @if (session('status'))<p class="border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif

        <section class="space-y-4" aria-labelledby="weekly-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div><h2 id="weekly-heading" class="text-2xl font-black">{{ __('booking_calendar.admin.weekly_title') }}</h2><p class="mt-2 text-easytire-muted">{{ __('booking_calendar.admin.weekly_help') }}</p></div>
                <a class="min-h-11 bg-easytire-brand px-5 py-3 text-sm font-bold text-white hover:bg-easytire-brand-strong" href="{{ route('admin.booking-calendar.intervals.create') }}">{{ __('booking_calendar.admin.add_interval') }}</a>
            </div>
            @if ($intervals->isEmpty())
                <p class="border border-dashed border-easytire-border bg-white p-8 text-center text-easytire-muted" data-testid="booking-calendar-unconfigured">{{ __('booking_calendar.admin.no_intervals') }}</p>
            @else
                <div class="easytire-card overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-easytire-ink text-white"><tr><th class="p-4">{{ __('booking_calendar.admin.weekday') }}</th><th class="p-4">{{ __('booking_calendar.admin.location') }}</th><th class="p-4">{{ __('booking_calendar.admin.hours') }}</th><th class="p-4">{{ __('booking_calendar.admin.slot_minutes') }}</th><th class="p-4">{{ __('booking_calendar.admin.capacity') }}</th><th class="p-4">{{ __('booking_calendar.admin.active') }}</th><th class="p-4"><span class="sr-only">{{ __('booking_calendar.admin.actions') }}</span></th></tr></thead><tbody class="divide-y divide-easytire-border">@foreach($intervals as $interval)<tr data-testid="booking-interval-row"><td class="p-4 font-bold">{{ __('booking_calendar.weekdays.'.$interval->weekday) }}</td><td class="p-4">{{ __('booking_calendar.locations.'.$interval->location) }}</td><td class="p-4">{{ substr($interval->start_time, 0, 5) }}–{{ substr($interval->end_time, 0, 5) }}</td><td class="p-4">{{ $interval->slot_minutes }}</td><td class="p-4">{{ $interval->capacity }}</td><td class="p-4">{{ $interval->is_active ? __('booking_calendar.admin.yes') : __('booking_calendar.admin.no') }}</td><td class="p-4 text-right"><a class="font-bold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.booking-calendar.intervals.edit', $interval) }}">{{ __('booking_calendar.admin.edit') }}</a></td></tr>@endforeach</tbody></table></div>
            @endif
        </section>

        <section class="space-y-4" aria-labelledby="blackout-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div><h2 id="blackout-heading" class="text-2xl font-black">{{ __('booking_calendar.admin.blackouts_title') }}</h2><p class="mt-2 text-easytire-muted">{{ __('booking_calendar.admin.blackouts_help') }}</p></div>
                <a class="min-h-11 border border-easytire-ink px-5 py-3 text-sm font-bold hover:border-easytire-brand hover:text-easytire-brand" href="{{ route('admin.booking-calendar.blackouts.create') }}">{{ __('booking_calendar.admin.add_blackout') }}</a>
            </div>
            @if ($blackouts->isEmpty())
                <p class="border border-dashed border-easytire-border bg-white p-8 text-center text-easytire-muted">{{ __('booking_calendar.admin.no_blackouts') }}</p>
            @else
                <div class="easytire-card overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-easytire-ink text-white"><tr><th class="p-4">{{ __('booking_calendar.admin.period') }}</th><th class="p-4">{{ __('booking_calendar.admin.location') }}</th><th class="p-4">{{ __('booking_calendar.admin.reason') }}</th><th class="p-4">{{ __('booking_calendar.admin.active') }}</th><th class="p-4"><span class="sr-only">{{ __('booking_calendar.admin.actions') }}</span></th></tr></thead><tbody class="divide-y divide-easytire-border">@foreach($blackouts as $blackout)<tr><td class="p-4 font-bold">{{ $blackout->starts_at->setTimezone($timezone)->format('Y-m-d H:i') }} – {{ $blackout->ends_at->setTimezone($timezone)->format('Y-m-d H:i') }}</td><td class="p-4">{{ __('booking_calendar.locations.'.$blackout->location) }}</td><td class="p-4">{{ $blackout->reason ?: '—' }}</td><td class="p-4">{{ $blackout->is_active ? __('booking_calendar.admin.yes') : __('booking_calendar.admin.no') }}</td><td class="p-4 text-right"><a class="font-bold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.booking-calendar.blackouts.edit', $blackout) }}">{{ __('booking_calendar.admin.edit') }}</a></td></tr>@endforeach</tbody></table></div>{{ $blackouts->links() }}
            @endif
        </section>
    </div>
</x-layouts.admin>
