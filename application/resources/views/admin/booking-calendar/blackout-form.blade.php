<x-layouts.admin :title="$blackout->exists ? __('booking_calendar.admin.edit_blackout') : __('booking_calendar.admin.add_blackout')">
    @php($localStart = $blackout->starts_at?->setTimezone($timezone)->format('Y-m-d\TH:i'))
    @php($localEnd = $blackout->ends_at?->setTimezone($timezone)->format('Y-m-d\TH:i'))
    <div class="max-w-3xl space-y-8">
        <header><a class="font-bold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.booking-calendar.index') }}">{{ __('booking_calendar.admin.back') }}</a><h1 class="mt-6 text-3xl font-black">{{ $blackout->exists ? __('booking_calendar.admin.edit_blackout') : __('booking_calendar.admin.add_blackout') }}</h1><p class="mt-3 text-easytire-muted">{{ __('booking_calendar.admin.blackout_form_help', ['timezone' => $timezone]) }}</p></header>
        @if (session('status'))<p class="border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif
        <form class="easytire-card grid gap-5 p-6 sm:grid-cols-2" method="post" action="{{ $blackout->exists ? route('admin.booking-calendar.blackouts.update', $blackout) : route('admin.booking-calendar.blackouts.store') }}" data-testid="booking-blackout-form">
            @csrf @if($blackout->exists) @method('PUT') @endif
            <label class="block text-sm font-bold sm:col-span-2">{{ __('booking_calendar.admin.location') }}<select class="easytire-input mt-2 min-h-11 w-full px-3" name="location" required>@foreach(['workshop', 'mobile', 'both'] as $location)<option value="{{ $location }}" @selected(old('location', $blackout->location) === $location)>{{ __('booking_calendar.locations.'.$location) }}</option>@endforeach</select></label>
            <label class="block text-sm font-bold">{{ __('booking_calendar.admin.starts_at') }}<input class="easytire-input mt-2 min-h-11 w-full px-3" name="starts_at" type="datetime-local" value="{{ old('starts_at', $localStart) }}" required>@error('starts_at')<span class="mt-2 block text-red-700">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-bold">{{ __('booking_calendar.admin.ends_at') }}<input class="easytire-input mt-2 min-h-11 w-full px-3" name="ends_at" type="datetime-local" value="{{ old('ends_at', $localEnd) }}" required>@error('ends_at')<span class="mt-2 block text-red-700">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-bold sm:col-span-2">{{ __('booking_calendar.admin.reason') }}<input class="easytire-input mt-2 min-h-11 w-full px-3" name="reason" value="{{ old('reason', $blackout->reason) }}" maxlength="255"></label>
            <label class="flex items-center gap-3 text-sm font-bold sm:col-span-2"><input name="is_active" type="checkbox" value="1" @checked((bool) old('is_active', $blackout->exists ? $blackout->is_active : true))>{{ __('booking_calendar.admin.active') }}</label>
            @if($errors->any() && !$errors->has('starts_at') && !$errors->has('ends_at'))<p class="text-sm font-bold text-red-700 sm:col-span-2" role="alert">{{ $errors->first() }}</p>@endif
            <div class="sm:col-span-2"><button class="min-h-11 bg-easytire-brand px-5 text-sm font-bold text-white hover:bg-easytire-brand-strong" type="submit">{{ __('booking_calendar.admin.save') }}</button></div>
        </form>
        @if($blackout->exists)<form method="post" action="{{ route('admin.booking-calendar.blackouts.destroy', $blackout) }}">@csrf @method('DELETE')<button class="min-h-11 border border-red-700 px-5 text-sm font-bold text-red-700" type="submit">{{ __('booking_calendar.admin.delete') }}</button></form>@endif
    </div>
</x-layouts.admin>
