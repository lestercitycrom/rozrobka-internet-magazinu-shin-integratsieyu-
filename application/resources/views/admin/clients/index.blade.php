<x-layouts.admin :title="__('clients.title')">
    <div class="space-y-8">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('clients.title') }}</h1>
                <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('clients.intro') }}</p>
            </div>
            <a class="et-btn et-btn--primary" href="{{ route('admin.clients.create') }}" data-testid="client-create">{{ __('clients.add') }}</a>
        </header>

        <form class="easytire-card flex flex-col gap-4 rounded-2xl p-5 sm:flex-row sm:items-end" method="get" action="{{ route('admin.clients.index') }}" data-testid="client-filters">
            <label class="block flex-1 text-sm font-bold">{{ __('clients.search') }}
                <input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('clients.search_hint') }}">
                <span class="mt-2 block font-normal text-easytire-muted">{{ __('clients.registration_history_notice') }}</span>
            </label>
            <div class="flex gap-3">
                <button class="et-btn et-btn--primary" type="submit">{{ __('clients.apply') }}</button>
                <a class="et-btn et-btn--outline" href="{{ route('admin.clients.index') }}">{{ __('clients.reset') }}</a>
            </div>
        </form>

        @if($clients->isEmpty())
            <p class="rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center text-easytire-muted">{{ __('clients.empty') }}</p>
        @else
            <div class="space-y-3 md:hidden">
                @foreach($clients as $client)
                    <article class="easytire-card rounded-2xl p-5">
                        <a class="font-black underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.clients.show', $client) }}">{{ $client->name }}</a>
                        <p class="mt-2"><a class="underline" href="tel:{{ $client->phone }}">{{ $client->phone }}</a></p>
                        @if($client->email)<p class="mt-1 break-words text-sm text-easytire-muted">{{ $client->email }}</p>@endif
                        <p class="mt-3 text-sm text-easytire-muted">{{ __('clients.requests_count', ['count' => $client->service_requests_count]) }}</p>
                    </article>
                @endforeach
            </div>
            <div class="easytire-card hidden overflow-x-auto rounded-2xl md:block">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-easytire-ink text-white"><tr><th class="p-4">{{ __('clients.name') }}</th><th class="p-4">{{ __('clients.phone') }}</th><th class="p-4">{{ __('clients.email') }}</th><th class="p-4">{{ __('clients.district') }}</th><th class="p-4">{{ __('clients.requests') }}</th></tr></thead>
                    <tbody class="divide-y divide-easytire-border">
                        @foreach($clients as $client)
                            <tr class="transition hover:bg-easytire-brand-soft/50">
                                <td class="p-4 font-bold"><a class="underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.clients.show', $client) }}">{{ $client->name }}</a></td>
                                <td class="p-4"><a class="underline" href="tel:{{ $client->phone }}">{{ $client->phone }}</a></td>
                                <td class="p-4 text-easytire-muted">{{ $client->email ?: '—' }}</td>
                                <td class="p-4 text-easytire-muted">{{ $client->district ?: '—' }}</td>
                                <td class="p-4">{{ $client->service_requests_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $clients->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
