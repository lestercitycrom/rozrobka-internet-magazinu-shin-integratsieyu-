<section class="et-admin-request-summary">
    <h3>{{ __('service_requests.history') }}</h3>
    @if($request->events->isEmpty())
        <p class="mt-2 text-sm text-easytire-muted">{{ __('service_requests.history_empty') }}</p>
    @else
        <ol class="mt-3 space-y-3" data-request-history>
            @foreach($request->events->take(25) as $event)
                <li class="border-l-2 border-easytire-border pl-3 text-sm">
                    <p><strong>{{ __('service_requests.command_'.$event->command) }}</strong> · {{ $event->created_at->setTimezone(config('service_booking.timezone'))->format('Y-m-d H:i') }}</p>
                    <p class="mt-1 text-easytire-muted">{{ $event->actor?->name ?: __('service_requests.unknown_actor') }}</p>
                    <p class="mt-1 break-words text-easytire-muted">{{ collect(array_keys($event->changes ?? []))->map(function ($field) {
                        $key = 'service_requests.history_field_'.$field;

                        return Illuminate\Support\Facades\Lang::has($key) ? __($key) : Illuminate\Support\Str::headline($field);
                    })->join(', ') }}</p>
                </li>
            @endforeach
        </ol>
    @endif
</section>
