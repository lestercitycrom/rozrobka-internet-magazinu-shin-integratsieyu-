@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $serviceIcon = static function ($service): string {
        $slug = mb_strtolower((string) data_get($service, 'slug'));

        return match (true) {
            str_contains($slug, 'mobil'), str_contains($slug, 'bobil'), str_contains($slug, 'varebil') => 'mobile-service',
            str_contains($slug, 'verksted'), str_contains($slug, 'batteri'), str_contains($slug, 'diagnose'), str_contains($slug, 'olje') => 'workshop',
            default => 'tire',
        };
    };
@endphp

<x-layouts.storefront :title="__('storefront.services_title')" active-section="services">
    <section class="et-page-hero">
        <img class="et-page-hero__photo" src="/brand/mobile-service.webp" width="1200" height="900" alt="{{ __('pro.home.hero_image_alt') }}">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ __('pro.nav.services') }}</span></div>
                <p class="et-eyebrow et-eyebrow--light">{{ __('pro.services.kicker') }}</p>
                <h1 class="et-h1">{{ __('pro.services.title') }}</h1>
                <p class="et-lead et-lead--light">{{ __('pro.services.intro') }}</p>
            </div>
        </div>
    </section>

    <section class="et-v3-section et-v3-section--white">
        <div class="et-container">
            <div class="et-v3-section-head">
                <div>
                    <p class="et-eyebrow">{{ __('pro.services.kicker') }}</p>
                    <h2 class="et-h2">{{ __('pro.home.services_title') }}</h2>
                    <p class="et-lead">{{ __('pro.home.services_text') }}</p>
                </div>
            </div>
            <div class="et-v3-services-index">
                @forelse ($services as $index => $service)
                    @php($publishedHero = data_get($service, 'hero.url'))
                    <article class="et-v3-service-row">
                        <a class="et-v3-service-row__media" href="{{ route($prefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'), $service->canonicalPath ?: $service->slug) }}" tabindex="-1" aria-hidden="true">
                            @if (is_string($publishedHero) && $publishedHero !== '')
                                <img src="{{ $publishedHero }}" width="1000" height="720" loading="lazy" alt="">
                            @else
                                <span class="et-v3-service-row__graphic et-v3-service-row__graphic--{{ $index % 3 }}">
                                    <x-icon :name="$serviceIcon($service)" :size="72" />
                                    <strong>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</strong>
                                </span>
                            @endif
                        </a>
                        <div class="et-v3-service-row__content">
                            <span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h2>{{ $service->title }}</h2>
                            @if ($service->excerpt)<p>{{ $service->excerpt }}</p>@endif
                            <a href="{{ route($prefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'), $service->canonicalPath ?: $service->slug) }}">{{ __('pro.home.service_link') }} <x-icon name="arrow" :size="18" /></a>
                        </div>
                    </article>
                @empty
                    <div class="et-empty-state" style="grid-column:1/-1">
                        <div><div class="et-empty-state__icon" aria-hidden="true">+</div><h2 class="et-h3" style="margin-top:1rem">{{ __('storefront.services_empty') }}</h2></div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="et-v3-section et-v3-section--ink">
        <div class="et-container et-v3-services-cta">
            <div class="et-v3-services-cta__content">
                <p class="et-eyebrow">{{ __('pro.services.request_kicker') }}</p>
                <h2 class="et-h2">{{ __('pro.services.request_title') }}</h2>
                <p class="et-lead">{{ __('pro.services.request_text') }}</p>
                <a class="et-btn et-btn--primary" href="tel:+4793958270">{{ __('pro.home.call') }} <x-icon name="phone" :size="18" /></a>
            </div>
            <div class="et-v3-services-cta__media">
                <img src="/brand/workshop-alt.webp" width="1400" height="900" loading="lazy" alt="{{ __('pro.content.workshop_image_alt') }}">
            </div>
        </div>
    </section>
</x-layouts.storefront>
