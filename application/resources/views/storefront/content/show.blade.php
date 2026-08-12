@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $slug = request()->route('slug');
    $isContact = $slug === 'kontakt';
    $isAbout = $slug === 'om-oss';
    $isLegal = in_array($slug, ['personvern', 'kjopsvilkar'], true);
    $fallbackHero = $isContact ? '/brand/exterior.webp' : ($isAbout ? '/brand/founders.webp' : '/brand/workshop-alt.webp');
    $heroUrl = $page->hero['url'] ?? $fallbackHero;
    $heroAlt = $page->hero['alt'] ?? ($isContact ? __('pro.content.contact_image_alt') : ($isAbout ? __('pro.content.about_image_alt') : 'EasyTire'));
    $storefrontSettings = null;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('storefront_settings')) {
            $storefrontSettings = app(\App\Modules\Content\ReadModel\StorefrontSettingsReadService::class)->current();
        }
    } catch (\Throwable) {
        $storefrontSettings = null;
    }
    $settingValue = static function (string $field, string $fallback) use ($storefrontSettings): string {
        $value = $storefrontSettings?->getAttribute($field);

        return filled($value) ? (string) $value : $fallback;
    };
    $phoneDisplay = $settingValue('phone_display', (string) config('storefront.phone_display'));
    $phoneHref = $settingValue('phone_href', (string) config('storefront.phone_href'));
    $contactEmail = $settingValue('email', (string) config('storefront.email'));
    $businessAddress = $settingValue('business_address', (string) config('storefront.business_address'));
    $mapPinAddress = $settingValue('map_pin_address', (string) config('storefront.map_pin_address'));
    $mapUrl = $settingValue('map_url', (string) config('storefront.map_url'));
    $mapEmbedUrl = \App\Support\GoogleMapsEmbedUrl::sanitize(
        data_get($page, 'map_embed_url')
        ?? data_get($page, 'metadata.map_embed_url')
        ?? $settingValue('map_embed_url', (string) config('storefront.map_embed_url', ''))
    );
@endphp

<x-layouts.storefront :title="$page->metaTitle ?? ('EasyTire | '.$page->title)" :description="$page->metaDescription" :active-section="$isAbout ? 'about' : ($isContact ? 'contact' : null)">
    @if ($isContact)
        <article>
            <section class="et-page-hero">
                <img data-testid="page-hero" class="et-page-hero__photo" src="{{ $heroUrl }}" width="1400" height="900" alt="{{ $heroAlt }}" @if($page->hero) loading="eager" @else loading="lazy" @endif>
                <div class="et-container et-page-hero__inner">
                    <div class="et-page-hero__content">
                        <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ $page->title }}</span></div>
                        <p class="et-eyebrow et-eyebrow--light">{{ __('pro.content.contact_kicker') }}</p>
                        <h1 class="et-h1">{{ __('pro.content.contact_title') }}</h1>
                        <p class="et-lead et-lead--light">{{ $page->excerpt ?: __('pro.content.contact_intro') }}</p>
                    </div>
                </div>
            </section>

            <section class="et-section et-section--white">
                <div class="et-container">
                    <div class="et-contact-grid">
                        <div class="et-contact-card">
                            <h2 class="et-h3">EasyTire Tromsø</h2>
                            <dl class="et-contact-list">
                                <div><dt>{{ __('pro.content.phone') }}</dt><dd><a href="tel:{{ $phoneHref }}">{{ $phoneDisplay }}</a></dd></div>
                                <div><dt>{{ __('pro.content.email') }}</dt><dd><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></dd></div>
                                <div><dt>{{ __('pro.content.address') }}</dt><dd>{{ $businessAddress }}<br><small>{{ __('project.contact.map_pin_note', ['address' => $mapPinAddress]) }}</small></dd></div>
                                <div><dt>{{ __('pro.content.hours') }}</dt><dd>{{ __('project.contact.hours') }}</dd></div>
                            </dl>
                            <div class="et-page-actions" style="margin-top:1rem">
                                <a class="et-btn et-btn--light" href="tel:{{ $phoneHref }}">{{ __('pro.content.call_now') }}</a>
                                <a class="et-btn et-btn--ghost" href="mailto:{{ $contactEmail }}">{{ __('pro.home.email') }}</a>
                            </div>
                        </div>

                        <div class="et-map" data-map-card data-testid="map-card">
                            @if ($mapEmbedUrl !== null)
                                <iframe class="et-map__frame" src="{{ $mapEmbedUrl }}" title="{{ __('pro.content.map_ready_title') }}" referrerpolicy="no-referrer-when-downgrade" allowfullscreen data-testid="map-iframe"></iframe>
                                <div class="et-map__loaded">
                                    <h2 class="et-h3">{{ __('pro.content.map_ready_title') }}</h2>
                                    <p>{{ __('pro.content.map_ready_text') }}</p>
                                    <a class="et-btn et-btn--primary" href="{{ $mapUrl }}" target="_blank" rel="noopener">{{ __('pro.content.map_link') }}</a>
                                </div>
                            @else
                                <div class="et-map__card">
                                    <div class="et-map__pin" aria-hidden="true"><x-icon name="location" :size="24" /></div>
                                    <h2 class="et-h3">{{ __('pro.content.map_title') }}</h2>
                                    <p>{{ __('pro.content.map_privacy') }}</p>
                                    <a class="et-btn et-btn--primary et-btn--full" href="{{ $mapUrl }}" target="_blank" rel="noopener" data-testid="map-external-only">{{ __('pro.content.map_options') }}</a>
                                    <a class="et-btn et-btn--link" href="{{ $mapUrl }}" target="_blank" rel="noopener">{{ __('pro.content.map_link') }}</a>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($page->body)
                        <div class="et-card et-card--flat" style="margin-top:1rem">
                            <div class="et-card__body et-richtext">{!! \Illuminate\Support\Str::markdown($page->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                        </div>
                    @endif

                    <div class="et-grid-2" style="margin-top:1rem">
                        <article class="et-card et-card--flat"><div class="et-card__body"><span class="et-card__meta">{{ __('pro.content.private_customer') }}</span><h2 class="et-h3">{{ __('pro.content.private_title') }}</h2><p class="et-copy">{{ __('pro.content.private_text') }}</p><a class="et-card__link" href="{{ route($prefix.'services.index') }}">{{ __('pro.nav.services') }}</a></div></article>
                        <article class="et-card et-card--flat"><div class="et-card__body"><span class="et-card__meta">{{ __('pro.content.business_customer') }}</span><h2 class="et-h3">{{ __('pro.content.business_title') }}</h2><p class="et-copy">{{ __('pro.content.business_text') }}</p><a class="et-card__link" href="mailto:{{ $contactEmail }}">{{ __('pro.home.email') }}</a></div></article>
                    </div>
                </div>
            </section>
        </article>
    @elseif ($isAbout)
        <article>
            <section class="et-page-hero">
                <img data-testid="page-hero" class="et-page-hero__photo" src="{{ $heroUrl }}" width="1400" height="1050" alt="{{ $heroAlt }}" @if($page->hero) loading="eager" @else loading="lazy" @endif>
                <div class="et-container et-page-hero__inner">
                    <div class="et-page-hero__content">
                        <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ $page->title }}</span></div>
                        <p class="et-eyebrow et-eyebrow--light">{{ __('pro.content.about_kicker') }}</p>
                        <h1 class="et-h1">{{ $page->title }}</h1>
                        @if ($page->excerpt)<p class="et-lead et-lead--light">{{ $page->excerpt }}</p>@endif
                    </div>
                </div>
            </section>

            <section class="et-section et-section--white">
                <div class="et-container et-split">
                    <div class="et-photo-pair">
                        <div class="et-photo-pair__main"><img src="/brand/founders.webp" width="1400" height="1050" loading="lazy" alt="{{ __('pro.content.about_image_alt') }}"></div>
                        <div class="et-photo-pair__small"><img src="/brand/mobile-service.webp" width="1200" height="900" loading="lazy" alt="{{ __('pro.content.mobile_image_alt') }}"></div>
                    </div>
                    <div>
                        <p class="et-eyebrow">EasyTire Tromsø</p>
                        <h2 class="et-h2">{{ __('pro.content.about_heading') }}</h2>
                        @if ($page->body)
                            <div class="et-richtext" style="margin-top:1.25rem">{!! \Illuminate\Support\Str::markdown($page->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                        @elseif ($page->excerpt)
                            <p class="et-lead">{{ $page->excerpt }}</p>
                        @endif
                    </div>
                </div>
            </section>

            <section class="et-section">
                <div class="et-container et-grid-2">
                    <article class="et-media-card"><img src="/brand/workshop.webp" width="1400" height="900" loading="lazy" alt="{{ __('pro.content.workshop_image_alt') }}"><div class="et-media-card__content"><h2 class="et-h2">{{ __('pro.content.workshop_title') }}</h2><p>{{ __('pro.content.workshop_text') }}</p></div></article>
                    <article class="et-media-card"><img src="/brand/hero-mobile.webp" width="1400" height="1000" loading="lazy" alt="{{ __('pro.content.mobile_image_alt') }}"><div class="et-media-card__content"><h2 class="et-h2">{{ __('pro.content.mobile_title') }}</h2><p>{{ __('pro.content.mobile_text') }}</p></div></article>
                </div>
            </section>
        </article>
    @else
        <article>
            <section @class(['et-page-hero', 'et-page-hero--light', 'et-page-hero--transactional' => $isLegal])>
                <div class="et-container et-page-hero__inner">
                    <div class="et-page-hero__content">
                        <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ $page->title }}</span></div>
                        <p class="et-eyebrow">{{ $isLegal ? __('pro.content.legal_kicker') : __('pro.content.page_kicker') }}</p>
                        <h1 class="et-h1">{{ $page->title }}</h1>
                        @if ($page->excerpt)<p class="et-lead">{{ $page->excerpt }}</p>@endif
                    </div>
                </div>
            </section>
            <section class="et-section et-section--white">
                <div class="et-container" style="max-width:900px">
                    @if ($page->hero)
                        <img data-testid="page-hero" class="et-cms-hero" src="{{ $heroUrl }}" alt="{{ $heroAlt }}" loading="lazy">
                    @endif
                    @if ($page->body)
                        <div class="et-richtext" @if($page->hero) style="margin-top:2rem" @endif>{!! \Illuminate\Support\Str::markdown($page->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @elseif ($page->excerpt)
                        <p class="et-lead">{{ $page->excerpt }}</p>
                    @endif
                </div>
            </section>
        </article>
    @endif
</x-layouts.storefront>
