<x-layouts.admin :title="__('health.title')">
    <div class="space-y-8">
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('health.title') }}</h1>
            <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('health.intro') }}</p>
        </header>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="{{ __('health.title') }}">
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.database') }}</p><p @class(['mt-3 text-xl font-black', 'text-green-700' => $health['database'], 'text-red-700' => ! $health['database']])>{{ $health['database'] ? __('health.ok') : __('health.fail') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.queue') }}</p><p class="mt-3 text-xl font-black">{{ $health['queue'] ?: __('health.none') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.cache') }}</p><p class="mt-3 text-xl font-black">{{ $health['cache'] ?: __('health.none') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.failed_jobs') }}</p><p @class(['mt-3 text-xl font-black', 'text-green-700' => $health['failed_jobs'] === 0, 'text-red-700' => is_int($health['failed_jobs']) && $health['failed_jobs'] > 0])>{{ $health['failed_jobs'] ?? __('health.none') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.outbox_pending') }}</p><p class="mt-3 text-xl font-black">{{ $health['outbox_pending'] ?? __('health.none') }}</p><p class="mt-1 text-sm text-easytire-muted">{{ __('health.outbox_failed') }}: {{ $health['outbox_failed'] ?? __('health.none') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.ndi_age_minutes') }}</p><p @class(['mt-3 text-xl font-black', 'text-green-700' => $health['ndi_fresh'], 'text-red-700' => ! $health['ndi_fresh']])>{{ $health['ndi_age_minutes'] === null ? __('health.none') : trans_choice('health_ui.minutes', $health['ndi_age_minutes'], ['count' => $health['ndi_age_minutes']]) }}</p><p class="mt-1 text-sm text-easytire-muted">{{ __('health_ui.import_status') }}: {{ $health['ndi_status'] ?: __('health.none') }}</p></article>
            @foreach (['scheduler' => 'scheduler_heartbeat', 'queue' => 'queue_heartbeat'] as $key => $label)
                <article class="easytire-card rounded-2xl p-5">
                    <p class="text-sm font-bold text-easytire-muted">{{ __('health.'.$label) }}</p>
                    <p @class(['mt-3 text-xl font-black', 'text-green-700' => $health[$key.'_healthy'], 'text-red-700' => ! $health[$key.'_healthy']])>{{ $health[$key.'_age_minutes'] === null ? __('health.none') : trans_choice('health_ui.minutes', $health[$key.'_age_minutes'], ['count' => $health[$key.'_age_minutes']]) }}</p>
                </article>
            @endforeach
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.disk_free_bytes') }}</p><p @class(['mt-3 text-xl font-black', 'text-green-700' => $health['disk_ok'], 'text-red-700' => ! $health['disk_ok']])>{{ $health['disk_free_bytes'] === null ? __('health.none') : number_format($health['disk_free_bytes'] / 1073741824, 1, ',', ' ') . ' GB' }}</p></article>
        </section>
    </div>
</x-layouts.admin>
