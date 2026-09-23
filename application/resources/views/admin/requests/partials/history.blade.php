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
                    <ul class="mt-1 space-y-1 break-words text-easytire-muted">
                        @foreach(($event->changes ?? []) as $field => $change)
                            @php
                                $labelKey = 'service_requests.history_field_'.$field;
                                $label = Illuminate\Support\Facades\Lang::has($labelKey) ? __($labelKey) : Illuminate\Support\Str::headline($field);
                                $safeFields = ['status', 'assigned_van', 'client_id', 'service_slug', 'fulfilment', 'preferred_date', 'preferred_window', 'booking_slot_key', 'booking_starts_at', 'booking_ends_at', 'dekkhotell_number', 'storage_number'];
                                $hasSafeValues = in_array($field, $safeFields, true) && is_array($change) && array_key_exists('old', $change) && array_key_exists('new', $change);
                                $formatHistoryValue = function (mixed $value) use ($field): string {
                                    if ($value === null || $value === '') return __('service_requests.history_empty_value');
                                    if ($field === 'status' && Illuminate\Support\Facades\Lang::has('service_requests.status_'.$value)) return __('service_requests.status_'.$value);
                                    if ($field === 'assigned_van') return Illuminate\Support\Facades\Lang::has('service_requests.assigned_van_'.$value) ? __('service_requests.assigned_van_'.$value) : (string) $value;
                                    if ($field === 'fulfilment' && Illuminate\Support\Facades\Lang::has('service_requests.fulfilment_'.$value)) return __('service_requests.fulfilment_'.$value);
                                    if ($field === 'client_id') return '#'.(string) $value;
                                    if (is_bool($value)) return $value ? __('service_requests.yes') : __('service_requests.no');

                                    return is_scalar($value) ? (string) $value : __('service_requests.history_changed_redacted');
                                };
                                $vehicleLabel = $field === 'storage_number' && filled($change['vehicle'] ?? null)
                                    ? ' · '.__('service_requests.history_vehicle_number', ['number' => $change['vehicle']])
                                    : '';
                            @endphp
                            <li>
                                <span class="font-bold">{{ $label }}{{ $vehicleLabel }}:</span>
                                @if($hasSafeValues)
                                    {{ __('service_requests.history_from_to', ['old' => $formatHistoryValue($change['old']), 'new' => $formatHistoryValue($change['new'])]) }}
                                @else
                                    {{ __('service_requests.history_changed_redacted') }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ol>
    @endif
</section>
