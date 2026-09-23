@php
    $quickAction = route('admin.service-requests.quick-action', ['serviceRequest' => $request]);
    $submittedCommand = old('command');
    $hasVersionConflict = $errors->has('expected_version')
        || (filled(old('command')) && filled(old('expected_version')) && (int) old('expected_version') !== (int) $request->operation_version);
    $conflictedCommand = $hasVersionConflict ? $submittedCommand : null;
    $submittedExpectedVersion = old('expected_version', $request->operation_version);
    $statusValue = $submittedCommand === 'status' ? old('status') : $request->status->value;
    $statusHasError = $submittedCommand === 'status' && $errors->has('status');
    $selectedVan = $submittedCommand === 'assigned_van' ? old('assigned_van') : $request->assigned_van;
    $vanHasError = $submittedCommand === 'assigned_van' && $errors->has('assigned_van');
    $noteValue = $submittedCommand === 'admin_note' ? old('admin_note') : $request->admin_note;
    $noteHasError = $submittedCommand === 'admin_note' && $errors->has('admin_note');
@endphp
<section class="et-admin-request-summary" data-quick-actions>
    <h3>{{ __('service_requests.quick_actions') }}</h3>
    <p class="mt-1 text-sm text-easytire-muted">{{ __('service_requests.quick_actions_intro') }}</p>

    <form class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" method="post" action="{{ $quickAction }}" data-status-quick-form data-reference="{{ $request->reference }}" @if($conflictedCommand === 'status') data-conflict-draft="true" @endif>
        @csrf
        @method('PATCH')
        <input type="hidden" name="command" value="status">
        <input type="hidden" name="command_id" value="{{ (string) Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="expected_version" value="{{ $conflictedCommand === 'status' ? $submittedExpectedVersion : $request->operation_version }}">
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <label class="text-sm font-bold">
            {{ __('service_requests.status') }}
            <select class="easytire-input mt-2 min-h-11 w-full px-3" name="status" @if($statusHasError) aria-invalid="true" aria-describedby="quick-status-error" @endif>
                @foreach(['new', 'contacted', 'closed', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected($statusValue === $status)>{{ __('service_requests.status_'.$status) }}</option>
                @endforeach
            </select>
            @if($statusHasError)<span class="et-admin-field-error" id="quick-status-error">{{ $errors->first('status') }}</span>@endif
        </label>
        <button class="et-btn et-btn--primary self-end" type="submit">{{ __('service_requests.apply_quick_action') }}</button>
        @if($conflictedCommand === 'status')<div class="sm:col-span-2">@include('admin.requests.partials.conflict-resolution', ['currentVersion' => $request->operation_version])</div>@endif
    </form>

    @if($request->fulfilment === 'mobile')
        <form class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" method="post" action="{{ $quickAction }}" @if($conflictedCommand === 'assigned_van') data-conflict-draft="true" @endif>
            @csrf
            @method('PATCH')
            <input type="hidden" name="command" value="assigned_van">
            <input type="hidden" name="command_id" value="{{ (string) Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="expected_version" value="{{ $conflictedCommand === 'assigned_van' ? $submittedExpectedVersion : $request->operation_version }}">
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
            <label class="text-sm font-bold">
                {{ __('service_requests.assigned_van') }}
                <select class="easytire-input mt-2 min-h-11 w-full px-3" name="assigned_van" @if($vanHasError) aria-invalid="true" aria-describedby="quick-van-error" @endif>
                    <option value="" @selected(blank($selectedVan))>{{ __('service_requests.assigned_van_unassigned') }}</option>
                    @foreach(['van_1', 'van_2'] as $van)
                        <option value="{{ $van }}" @selected($selectedVan === $van)>{{ __('service_requests.assigned_van_'.$van) }}</option>
                    @endforeach
                </select>
                @if($vanHasError)<span class="et-admin-field-error" id="quick-van-error">{{ $errors->first('assigned_van') }}</span>@endif
            </label>
            <button class="et-btn et-btn--primary self-end" type="submit">{{ __('service_requests.apply_quick_action') }}</button>
            @if($conflictedCommand === 'assigned_van')<div class="sm:col-span-2">@include('admin.requests.partials.conflict-resolution', ['currentVersion' => $request->operation_version])</div>@endif
        </form>
    @endif

    <form class="mt-4" method="post" action="{{ $quickAction }}" @if($conflictedCommand === 'admin_note') data-conflict-draft="true" @endif>
        @csrf
        @method('PATCH')
        <input type="hidden" name="command" value="admin_note">
        <input type="hidden" name="command_id" value="{{ (string) Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="expected_version" value="{{ $conflictedCommand === 'admin_note' ? $submittedExpectedVersion : $request->operation_version }}">
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <label class="text-sm font-bold">
            {{ __('service_requests.admin_note') }}
            <textarea class="easytire-input mt-2 min-h-24 w-full px-3 py-2" name="admin_note" maxlength="4000" @if($noteHasError) aria-invalid="true" aria-describedby="quick-note-error" @endif>{{ $noteValue }}</textarea>
            @if($noteHasError)<span class="et-admin-field-error" id="quick-note-error">{{ $errors->first('admin_note') }}</span>@endif
        </label>
        <button class="et-btn et-btn--primary mt-3" type="submit">{{ __('service_requests.save_note') }}</button>
        @if($conflictedCommand === 'admin_note')@include('admin.requests.partials.conflict-resolution', ['currentVersion' => $request->operation_version])@endif
    </form>

    @foreach($vehicles as $index => $vehicle)
        @if(in_array($vehicle['storage_plan'] ?? 'none', ['new', 'renew', 'last'], true))
            @php
                $storageWasSubmitted = $submittedCommand === 'storage_number' && (int) old('vehicle_index', -1) === $index;
                $storageValue = $storageWasSubmitted ? old('storage_number') : ($vehicle['storage_number'] ?? '');
                $storageHasError = $storageWasSubmitted && $errors->has('storage_number');
                $storageConflict = $conflictedCommand === 'storage_number' && $storageWasSubmitted;
            @endphp
            <form class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" method="post" action="{{ $quickAction }}" @if($storageConflict) data-conflict-draft="true" @endif>
                @csrf
                @method('PATCH')
                <input type="hidden" name="command" value="storage_number">
                <input type="hidden" name="command_id" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="expected_version" value="{{ $storageConflict ? $submittedExpectedVersion : $request->operation_version }}">
                <input type="hidden" name="return_to" value="{{ $returnTo }}">
                <input type="hidden" name="vehicle_index" value="{{ $index }}">
                <label class="text-sm font-bold">
                    {{ __('service_requests.storage_number_for_vehicle', ['number' => $index + 1]) }}
                    <input class="easytire-input mt-2 min-h-11 w-full px-3" name="storage_number" value="{{ $storageValue }}" maxlength="64" @if($storageHasError) aria-invalid="true" aria-describedby="quick-storage-{{ $index }}-error" @endif>
                    @if($storageHasError)<span class="et-admin-field-error" id="quick-storage-{{ $index }}-error">{{ $errors->first('storage_number') }}</span>@endif
                </label>
                <button class="et-btn et-btn--primary self-end" type="submit">{{ __('service_requests.apply_quick_action') }}</button>
                @if($storageConflict)<div class="sm:col-span-2">@include('admin.requests.partials.conflict-resolution', ['currentVersion' => $request->operation_version])</div>@endif
            </form>
        @endif
    @endforeach
</section>
