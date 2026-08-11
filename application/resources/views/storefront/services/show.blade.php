@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $heroUrl = $service->hero['url'] ?? '/brand/workshop-alt.webp';
    $heroAlt = $service->hero['alt'] ?? 'EasyTire verksted i Tromsø';
@endphp

<x-layouts.storefront :title="$service->seoTitle ?? ('EasyTire | '.$service->title)" :description="$service->seoDescription" :canonical="$canonicalUrl ?? null" active-section="services">
    <article>
        <section class="et-service-hero">
            <div class="et-service-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span><a href="{{ route($prefix.'services.index') }}">{{ __('pro.nav.services') }}</a></span><span>{{ $service->title }}</span></div>
                <p class="et-eyebrow et-eyebrow--light">{{ __('pro.services.kicker') }}</p>
                <h1 class="et-h1">{{ $service->title }}</h1>
                @if ($service->excerpt)<p class="et-lead et-lead--light">{{ $service->excerpt }}</p>@endif
                <div class="et-page-actions" style="margin-top:1.5rem">
                    <a class="et-btn et-btn--primary" href="{{ route($prefix.'requests.create', $service->slug) }}" data-testid="service-hero-request">{{ __('services.request_cta') }}</a>
                    <a class="et-btn et-btn--ghost" href="tel:+4793958270">{{ __('pro.home.call') }}</a>
                </div>
            </div>
            <div class="et-service-hero__media">
                <img data-testid="page-hero" src="{{ $heroUrl }}" alt="{{ $heroAlt }}" @if($service->hero) loading="eager" @else loading="lazy" @endif>
            </div>
        </section>

        <div class="et-service-anchor">
            <div class="et-container et-service-anchor__inner">
                <nav aria-label="{{ $service->title }}">
                    <a href="#service-details">{{ __('storefront.product_information') }}</a>
                    <a href="#service-request">{{ __('pro.services.request_kicker') }}</a>
                </nav>
                <a class="et-btn et-btn--primary et-btn--small" href="{{ route($prefix.'requests.create', $service->slug) }}" data-testid="service-anchor-request">{{ __('services.request_cta') }}</a>
            </div>
        </div>

        <section class="et-section et-section--white" id="service-details">
            <div class="et-container et-split">
                <div>
                    <p class="et-eyebrow">{{ __('pro.services.kicker') }}</p>
                    <h2 class="et-h2">{{ $service->title }}</h2>
                    @if ($service->body)
                        <div class="et-richtext" style="margin-top:1.5rem">{!! \Illuminate\Support\Str::markdown($service->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @elseif ($service->excerpt)
                        <p class="et-lead">{{ $service->excerpt }}</p>
                    @endif
                </div>
                <aside class="et-sticky-cta" id="service-request">
                    <p class="et-eyebrow">{{ __('pro.services.request_kicker') }}</p>
                    <h2 class="et-h3" style="margin-top:.6rem">{{ __('pro.services.request_title') }}</h2>
                    <p class="et-copy">{{ __('services.request_note') }}</p>
                    <div class="et-alert et-alert--info" style="margin-block:1rem">
                        <span class="et-alert__icon" aria-hidden="true">i</span>
                        <div>{{ __('booking.intro') }}</div>
                    </div>
                    <a class="et-btn et-btn--primary et-btn--full" href="{{ route($prefix.'requests.create', $service->slug) }}" data-testid="service-aside-request">{{ __('services.request_cta') }}</a>
                </aside>
            </div>
        </section>
    </article>
</x-layouts.storefront>
