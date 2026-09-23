@php($editing = $client->exists)
<x-layouts.admin :title="$editing ? __('clients.edit') : __('clients.create')">
    <div class="max-w-4xl space-y-8">
        <header>
            <a class="inline-flex min-h-11 items-center underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ $editing ? route('admin.clients.show', $client) : route('admin.clients.index') }}">{{ __('clients.back') }}</a>
            <h1 class="mt-5 text-3xl font-black tracking-tight sm:text-4xl">{{ $editing ? __('clients.edit') : __('clients.create') }}</h1>
        </header>

        @if($errors->any())
            <div class="rounded-xl border border-red-300 bg-red-50 p-5 text-sm text-red-900" role="alert" data-testid="client-form-errors">
                <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if($linkRequest)
            <p class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">{{ __('clients.link_request', ['reference' => $linkRequest->reference]) }}</p>
        @endif

        @if(($duplicateClients ?? collect())->isNotEmpty())
            <section class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status" data-testid="duplicate-client-warning">
                <h2 class="font-black">{{ __('clients.possible_duplicates') }}</h2>
                <p class="mt-1">{{ __('clients.possible_duplicates_intro') }}</p>
                <ul class="mt-3 space-y-2">
                    @foreach($duplicateClients as $duplicateClient)
                        <li>
                            <a class="font-bold underline decoration-2 underline-offset-4" href="{{ route('admin.clients.show', $duplicateClient) }}" target="_blank" rel="noopener noreferrer">
                                {{ $duplicateClient->name }} · {{ $duplicateClient->phone }}@if($duplicateClient->email) · {{ $duplicateClient->email }}@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 font-bold">{{ __('clients.possible_duplicates_no_merge') }}</p>
            </section>
        @endif

        <form class="easytire-card rounded-2xl p-6" method="post" action="{{ $editing ? route('admin.clients.update', $client) : route('admin.clients.store') }}" data-testid="client-form">
            @csrf
            @if($editing) @method('PUT') @endif
            @if($linkRequest)
                <input type="hidden" name="link_request_id" value="{{ $linkRequest->id }}">
                <input type="hidden" name="expected_version" value="{{ $linkRequest->operation_version }}">
                <input type="hidden" name="command_id" value="{{ old('command_id', (string) Illuminate\Support\Str::uuid()) }}">
                @if(filled($returnTo ?? null))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
            @endif
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block text-sm font-bold" for="client-name">{{ __('clients.name') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" id="client-name" name="name" value="{{ old('name', $client->name) }}" required maxlength="120" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="client-name-error" @enderror>@error('name')<span class="et-admin-field-error" id="client-name-error">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-bold" for="client-phone">{{ __('clients.phone') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" id="client-phone" name="phone" value="{{ old('phone', $client->phone) }}" required maxlength="24" inputmode="tel" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="client-phone-error" @enderror>@error('phone')<span class="et-admin-field-error" id="client-phone-error">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-bold" for="client-email">{{ __('clients.email') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" id="client-email" name="email" value="{{ old('email', $client->email) }}" maxlength="255" type="email" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="client-email-error" @enderror>@error('email')<span class="et-admin-field-error" id="client-email-error">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-bold" for="client-district">{{ __('clients.district') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" id="client-district" name="district" value="{{ old('district', $client->district) }}" maxlength="120" @error('district') aria-invalid="true" aria-describedby="client-district-error" @enderror>@error('district')<span class="et-admin-field-error" id="client-district-error">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-bold sm:col-span-2" for="client-address">{{ __('clients.address') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" id="client-address" name="address" value="{{ old('address', $client->address) }}" maxlength="255" autocomplete="street-address" @error('address') aria-invalid="true" aria-describedby="client-address-error" @enderror>@error('address')<span class="et-admin-field-error" id="client-address-error">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-bold sm:col-span-2" for="client-note">{{ __('clients.note') }}<textarea class="easytire-input mt-2 min-h-36 w-full rounded-lg px-3 py-3" id="client-note" name="note" maxlength="4000" @error('note') aria-invalid="true" aria-describedby="client-note-error" @enderror>{{ old('note', $client->note) }}</textarea>@error('note')<span class="et-admin-field-error" id="client-note-error">{{ $message }}</span>@enderror</label>
            </div>
            <button class="et-btn et-btn--primary mt-6" type="submit" data-testid="client-save">{{ __('clients.save') }}</button>
        </form>
    </div>
</x-layouts.admin>
