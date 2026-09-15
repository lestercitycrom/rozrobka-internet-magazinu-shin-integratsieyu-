<x-layouts.admin :title="$client->name">
    <div class="max-w-6xl space-y-8">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a class="inline-flex min-h-11 items-center underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.clients.index') }}">{{ __('clients.back') }}</a>
                <h1 class="mt-5 text-3xl font-black tracking-tight sm:text-4xl">{{ $client->name }}</h1>
            </div>
            <div class="flex flex-wrap gap-3">
                <a class="et-btn et-btn--outline" href="{{ route('admin.clients.edit', $client) }}">{{ __('clients.edit') }}</a>
                <a class="et-btn et-btn--primary" href="{{ route('admin.service-requests.create', ['client' => $client->id]) }}" data-testid="client-create-request">{{ __('clients.create_request') }}</a>
            </div>
        </header>

        @if(session('status'))<p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="easytire-card rounded-2xl p-6">
                <h2 class="text-lg font-black">{{ __('service_requests.contact') }}</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div><dt class="text-easytire-muted">{{ __('clients.phone') }}</dt><dd class="mt-1 font-bold"><a class="underline" href="tel:{{ $client->phone }}">{{ $client->phone }}</a></dd></div>
                    <div><dt class="text-easytire-muted">{{ __('clients.email') }}</dt><dd class="mt-1">{{ $client->email ?: '—' }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('clients.address') }}</dt><dd class="mt-1">{{ $client->address ?: '—' }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('clients.district') }}</dt><dd class="mt-1">{{ $client->district ?: '—' }}</dd></div>
                </dl>
            </section>
            <section class="easytire-card rounded-2xl p-6">
                <h2 class="text-lg font-black">{{ __('clients.note') }}</h2>
                <p class="mt-5 whitespace-pre-wrap text-sm">{{ $client->note ?: '—' }}</p>
                <dl class="mt-6 grid gap-4 border-t border-easytire-border pt-5 text-sm sm:grid-cols-2">
                    <div><dt class="text-easytire-muted">{{ __('clients.created_by') }}</dt><dd class="mt-1">{{ $client->createdBy?->name ?: '—' }}</dd></div>
                    <div><dt class="text-easytire-muted">{{ __('clients.created_at') }}</dt><dd class="mt-1">{{ $client->created_at->format('Y-m-d H:i') }}</dd></div>
                </dl>
            </section>
        </div>

        <section class="easytire-card rounded-2xl p-6">
            <h2 class="text-xl font-black">{{ __('clients.requests') }}</h2>
            <p class="mt-2 text-sm text-easytire-muted">{{ __('clients.registration_history_notice') }}</p>
            @if($linkedRequests->isEmpty())
                <p class="mt-5 text-easytire-muted">{{ __('clients.no_requests') }}</p>
            @else
                <div class="mt-5 divide-y divide-easytire-border">
                    @foreach($linkedRequests as $request)
                        @php
                            $registrations = collect($request->vehicles ?? [])->pluck('registration_number')->filter();
                            if (filled($request->registration_number)) {
                                $registrations->prepend($request->registration_number);
                            }
                            $registrations = $registrations->unique()->values();
                        @endphp
                        <div class="flex flex-col gap-2 py-4 first:pt-0 sm:flex-row sm:items-center sm:justify-between">
                            <div><a class="font-black underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-requests.show', $request) }}">{{ $request->reference }}</a><p class="mt-1 text-sm text-easytire-muted">{{ $serviceTitles[$request->service_slug] ?? $request->service_slug }} · {{ $request->created_at->format('Y-m-d H:i') }}</p>@if($registrations->isNotEmpty())<p class="mt-1 text-sm"><strong>{{ __('clients.registration_history_label') }}:</strong> {{ $registrations->join(', ') }}</p>@endif</div>
                            <span class="text-sm font-bold">{{ __('service_requests.status_'.$request->status->value) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5">{{ $linkedRequests->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.admin>
