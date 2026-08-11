@php($prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.')

<x-layouts.storefront :title="__('storefront.services_title')" active-section="services">
    <section class="et-page-hero">
        <img class="et-page-hero__photo" src="/brand/mobile-service.webp" width="1200" height="900" alt="EasyTire mobil service og verksted">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ __('pro.nav.services') }}</span></div>
                <p class="et-eyebrow et-eyebrow--light">{{ __('pro.services.kicker') }}</p>
                <h1 class="et-h1">{{ __('pro.services.title') }}</h1>
                <p class="et-lead et-lead--light">{{ __('pro.services.intro') }}</p>
            </div>
        </div>
    </section>

    <section class="et-section et-section--white">
        <div class="et-container">
            <div class="et-service-grid" style="margin-top:0">
                @forelse ($services as $index => $service)
                    <article @class(['et-service-card', 'et-service-card--feature' => $index === 0])>
                        <div class="et-service-card__top">
                            <span class="et-service-card__index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="et-service-card__icon" aria-hidden="true">{{ $index === 0 ? '↻' : ($index === 1 ? 'V' : ($index === 2 ? 'D' : '+')) }}</span>
                        </div>
                        <div class="et-service-card__body">
                            <h2>{{ $service->title }}</h2>
                            @if ($service->excerpt)<p>{{ $service->excerpt }}</p>@endif
                            <a href="{{ route($prefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'), $service->canonicalPath ?: $service->slug) }}">{{ __('pro.home.service_link') }}</a>
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

    <section class="et-section et-section--grid">
        <div class="et-container et-split">
            <div>
                <p class="et-eyebrow">{{ __('pro.services.request_kicker') }}</p>
                <h2 class="et-h2">{{ __('pro.services.request_title') }}</h2>
                <p class="et-lead">{{ __('pro.services.request_text') }}</p>
                <a class="et-btn et-btn--primary" href="tel:+4793958270" style="margin-top:1.4rem">{{ __('pro.home.call') }}</a>
            </div>
            <div class="et-photo-pair">
                <div class="et-photo-pair__main"><img src="/brand/workshop-alt.webp" width="1400" height="900" loading="lazy" alt="EasyTire verksted"></div>
                <div class="et-photo-pair__small"><img src="/brand/exterior.webp" width="1400" height="900" loading="lazy" alt="EasyTire varebil og verksted"></div>
            </div>
        </div>
    </section>
</x-layouts.storefront>
