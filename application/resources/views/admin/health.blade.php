<x-layouts.admin :title="__('health.title')">
    <div class="space-y-8">
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('health.title') }}</h1>
            <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('health.intro') }}</p>
        </header>
        <section class="et-health-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="{{ __('health.title') }}">
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.database') }}</p><p @class(['et-health-value mt-3 text-xl font-black', 'et-health-value--success' => $health['database'], 'et-health-value--error' => ! $health['database']])>{{ $health['database'] ? __('health.ok') : __('health.fail') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.queue') }}</p><p class="et-health-value et-health-value--neutral mt-3 text-xl font-black">{{ $health['queue'] ?: __('health.none') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.cache') }}</p><p class="et-health-value et-health-value--neutral mt-3 text-xl font-black">{{ $health['cache'] ?: __('health.none') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.failed_jobs') }}</p><p @class(['et-health-value mt-3 text-xl font-black', 'et-health-value--success' => $health['failed_jobs'] === 0, 'et-health-value--error' => is_int($health['failed_jobs']) && $health['failed_jobs'] > 0, 'et-health-value--neutral' => $health['failed_jobs'] === null])>{{ $health['failed_jobs'] ?? __('health.none') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.outbox_pending') }}</p><p class="mt-3 text-xl font-black">{{ $health['outbox_pending'] ?? __('health.none') }}</p><p class="mt-1 text-sm text-easytire-muted">{{ __('health.outbox_failed') }}: {{ $health['outbox_failed'] ?? __('health.none') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.ndi_media') }}</p><p @class(['et-health-value mt-3 text-xl font-black', 'et-health-value--success' => $health['ndi_media_failed'] === 0, 'et-health-value--error' => is_int($health['ndi_media_failed']) && $health['ndi_media_failed'] > 0, 'et-health-value--neutral' => $health['ndi_media_failed'] === null])>{{ __('health.ndi_media_failed') }}: {{ $health['ndi_media_failed'] ?? __('health.none') }}</p><p class="mt-1 text-sm text-easytire-muted">{{ __('health.ndi_media_pending') }}: {{ $health['ndi_media_pending'] ?? __('health.none') }}</p></article>
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.ndi_age_minutes') }}</p><p @class(['et-health-value mt-3 text-xl font-black', 'et-health-value--success' => $health['ndi_fresh'], 'et-health-value--error' => ! $health['ndi_fresh']])>{{ $health['ndi_age_minutes'] === null ? __('health.none') : trans_choice('health_ui.minutes', $health['ndi_age_minutes'], ['count' => $health['ndi_age_minutes']]) }}</p><p class="mt-1 text-sm text-easytire-muted">{{ __('health_ui.import_status') }}: {{ $health['ndi_status'] ?: __('health.none') }}</p></article>
            @foreach (['scheduler' => 'scheduler_heartbeat', 'queue' => 'queue_heartbeat'] as $key => $label)
                <article class="easytire-card rounded-2xl p-5">
                    <p class="text-sm font-bold text-easytire-muted">{{ __('health.'.$label) }}</p>
                    <p @class(['et-health-value mt-3 text-xl font-black', 'et-health-value--success' => $health[$key.'_healthy'], 'et-health-value--error' => ! $health[$key.'_healthy']])>{{ $health[$key.'_age_minutes'] === null ? __('health.none') : trans_choice('health_ui.minutes', $health[$key.'_age_minutes'], ['count' => $health[$key.'_age_minutes']]) }}</p>
                </article>
            @endforeach
            <article class="easytire-card rounded-2xl p-5"><p class="text-sm font-bold text-easytire-muted">{{ __('health.disk_free_bytes') }}</p><p @class(['et-health-value mt-3 text-xl font-black', 'et-health-value--success' => $health['disk_ok'], 'et-health-value--error' => ! $health['disk_ok']])>{{ $health['disk_free_bytes'] === null ? __('health.none') : number_format($health['disk_free_bytes'] / 1073741824, 1, ',', ' ') . ' GB' }}</p></article>
            <article class="easytire-card rounded-2xl p-5" data-testid="payment-health">
                <p class="text-sm font-bold text-easytire-muted">{{ __('integration_health.payment') }}</p>
                <p @class(['et-health-value mt-3 text-xl font-black', 'et-health-value--success' => $health['payment_enabled'] && $health['payment_configured'] && $health['payment_callback_protected'], 'et-health-value--error' => $health['payment_enabled'] && (! $health['payment_configured'] || ! $health['payment_callback_protected']), 'et-health-value--neutral' => ! $health['payment_enabled']])>
                    {{ $health['payment_enabled'] ? ($health['payment_configured'] && $health['payment_callback_protected'] ? __('integration_health.ready') : __('integration_health.incomplete')) : __('integration_health.disabled') }}
                </p>
                <p class="mt-1 text-sm text-easytire-muted">{{ strtoupper($health['payment_provider'] ?: '—') }} · {{ __('integration_health.'.$health['payment_environment']) }}</p>
            </article>
            <article class="easytire-card rounded-2xl p-5" data-testid="notion-health">
                <p class="text-sm font-bold text-easytire-muted">{{ __('integration_health.notion') }}</p>
                <p @class(['et-health-value mt-3 text-xl font-black', 'et-health-value--success' => $health['notion_enabled'] && $health['notion_configured'], 'et-health-value--error' => $health['notion_enabled'] && ! $health['notion_configured'], 'et-health-value--neutral' => ! $health['notion_enabled']])>
                    {{ $health['notion_enabled'] ? ($health['notion_configured'] ? __('integration_health.ready') : __('integration_health.incomplete')) : __('integration_health.disabled') }}
                </p>
                <p class="mt-1 text-sm text-easytire-muted">{{ __('integration_health.notion_after_capture') }}</p>
            </article>
            <article class="easytire-card rounded-2xl p-5" data-testid="ndi-sandbox-health">
                <p class="text-sm font-bold text-easytire-muted">{{ __('integration_health.ndi_sandbox') }}</p>
                <p @class(['et-health-value mt-3 text-xl font-black', 'et-health-value--success' => $health['ndi_sandbox_ready'], 'et-health-value--error' => $health['ndi_sandbox_enabled'] && ! $health['ndi_sandbox_ready'], 'et-health-value--neutral' => ! $health['ndi_sandbox_enabled']])>
                    {{ $health['ndi_sandbox_enabled'] ? ($health['ndi_sandbox_ready'] ? __('integration_health.ready') : __('integration_health.incomplete')) : __('integration_health.disabled') }}
                </p>
                <p class="mt-1 text-sm text-easytire-muted">{{ $health['ndi_sandbox_fixture_active'] ? __('integration_health.ndi_sandbox_fixture_active') : __('integration_health.ndi_sandbox_fixture_missing') }}</p>
            </article>
        </section>
    </div>
</x-layouts.admin>
