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

        <form class="easytire-card rounded-2xl p-6" method="post" action="{{ $editing ? route('admin.clients.update', $client) : route('admin.clients.store') }}" data-testid="client-form">
            @csrf
            @if($editing) @method('PUT') @endif
            @if($linkRequest)<input type="hidden" name="link_request_id" value="{{ $linkRequest->id }}">@endif
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block text-sm font-bold">{{ __('clients.name') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="name" value="{{ old('name', $client->name) }}" required maxlength="120" autocomplete="name"></label>
                <label class="block text-sm font-bold">{{ __('clients.phone') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="phone" value="{{ old('phone', $client->phone) }}" required maxlength="24" inputmode="tel" autocomplete="tel"></label>
                <label class="block text-sm font-bold">{{ __('clients.email') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="email" value="{{ old('email', $client->email) }}" maxlength="255" type="email" autocomplete="email"></label>
                <label class="block text-sm font-bold">{{ __('clients.district') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="district" value="{{ old('district', $client->district) }}" maxlength="120"></label>
                <label class="block text-sm font-bold sm:col-span-2">{{ __('clients.address') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="address" value="{{ old('address', $client->address) }}" maxlength="255" autocomplete="street-address"></label>
                <label class="block text-sm font-bold sm:col-span-2">{{ __('clients.note') }}<textarea class="easytire-input mt-2 min-h-36 w-full rounded-lg px-3 py-3" name="note" maxlength="4000">{{ old('note', $client->note) }}</textarea></label>
            </div>
            <button class="et-btn et-btn--primary mt-6" type="submit" data-testid="client-save">{{ __('clients.save') }}</button>
        </form>
    </div>
</x-layouts.admin>
