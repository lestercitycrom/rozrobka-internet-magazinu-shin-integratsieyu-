@php($prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.')
<x-layouts.storefront :title="$service->seoTitle ?? ('EasyTire | '.$service->title)" :description="$service->seoDescription">
    <article class="mx-auto max-w-4xl px-5 py-12 sm:px-8">
        <a href="{{ route($prefix.'services.index') }}" class="font-bold text-easytire-brand underline">{{ __('storefront.back_services') }}</a>
        <h1 class="mt-6 text-4xl font-black">{{ $service->title }}</h1>
        @if($service->hero)<img data-testid="page-hero" src="{{ $service->hero['url'] }}" alt="{{ $service->hero['alt'] }}" loading="lazy" class="mt-6 aspect-video w-full rounded-xl object-cover">@endif
        @if ($service->excerpt)
            <p class="mt-5 text-xl text-easytire-muted">{{ $service->excerpt }}</p>
        @endif
        @if ($service->body)
            <div class="prose mt-8 max-w-none">{!! nl2br(e($service->body)) !!}</div>
        @endif
        <aside class="mt-10 rounded-2xl border border-easytire-border bg-easytire-brand-soft p-5 sm:flex sm:items-center sm:justify-between sm:gap-6 sm:p-6">
            <p class="max-w-xl text-sm leading-6 text-easytire-muted">{{ __('services.request_note') }}</p>
            <a href="{{ route($prefix.'requests.create', $service->slug) }}" class="mt-4 inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-easytire-brand px-5 font-bold text-white transition-colors hover:bg-easytire-brand-strong sm:mt-0">{{ __('services.request_cta') }}</a>
        </aside>
    </article>
</x-layouts.storefront>
