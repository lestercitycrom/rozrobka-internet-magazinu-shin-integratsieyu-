@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $contact = app(\App\Modules\Content\ReadModel\StorefrontSettingsReadService::class)->contact();
    $publishedServiceSlugs = collect($services)->pluck('slug');
    $serviceDirectory = collect([
        ['slug' => 'interiorrens', 'id' => 'service-interior-cleaning', 'title' => __('pro.nav.interior_cleaning'), 'text' => __('pro.services.interior_cleaning_text'), 'href' => route($prefix.'content.show', 'kontakt')],
        ['slug' => 'polering-lakkorrigering', 'id' => 'service-polishing-paint', 'title' => __('pro.nav.polishing_paint'), 'text' => __('pro.services.polishing_paint_text'), 'href' => route($prefix.'content.show', 'kontakt')],
        ['slug' => 'utelasing-bil', 'id' => 'service-lockout', 'title' => __('pro.nav.lockout'), 'text' => __('pro.services.lockout_text'), 'href' => 'tel:'.$contact['phone_href']],
    ])->reject(fn (array $entry): bool => $publishedServiceSlugs->contains($entry['slug']));
@endphp

<x-layouts.storefront :title="__('storefront.services_title')" active-section="services">
    <section class="et-page-hero et-page-hero--transactional">
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

    <section class="et-v3-section et-v3-section--white" id="additional-services">
        <div class="et-container">
            <div class="et-services-catalog" data-testid="services-catalog">
                @forelse ($services as $index => $service)
                    @php
                        $destination = route($prefix.($service->canonicalPath ? 'seo-services.show' : 'services.show'), $service->canonicalPath ?: $service->slug);
                        $media = $serviceMedia[$service->slug];
                    @endphp
                    <article class="et-service-offer{{ $media['feature'] ? ' et-service-offer--feature' : '' }}" data-testid="service-card" data-service-slug="{{ $service->slug }}" data-media-kind="{{ $media['kind'] }}" data-media-source="{{ $media['source'] }}">
                        <a class="et-service-card__media et-service-card__media--{{ $media['crop'] }}" href="{{ $destination }}" tabindex="-1" aria-hidden="true">
                            @if ($media['url'])
                                <img src="{{ $media['url'] }}" width="1000" height="720" loading="lazy" alt="">
                            @else
                                <span class="et-service-card__fallback">
                                    <img src="/brand/easytire-mark.svg" width="88" height="88" alt="">
                                    <span>{{ __('pro.services.kicker') }}</span>
                                </span>
                            @endif
                        </a>
                        <div class="et-service-card__content">
                            <p class="et-service-card__meta">{{ __('pro.services.kicker') }}</p>
                            <h2><a href="{{ $destination }}">{{ $service->title }}</a></h2>
                            @if ($service->excerpt)<p>{{ $service->excerpt }}</p>@endif
                            <a class="et-service-card__link et-action-link" href="{{ $destination }}" aria-label="{{ __('pro.home.service_link') }}: {{ $service->title }}"><span class="et-action-link__label">{{ __('pro.home.service_link') }}</span><x-icon name="arrow" :size="16" /></a>
                        </div>
                    </article>
                @empty
                    <div class="et-empty-state et-services-catalog__empty">
                        <div><div class="et-empty-state__icon" aria-hidden="true">+</div><h2 class="et-h3">{{ __('storefront.services_empty') }}</h2></div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <span class="et-section-anchor" id="bilpleie" aria-hidden="true"></span>
    @if ($serviceDirectory->isNotEmpty())
        <section class="et-v3-section et-v3-section--soft et-service-directory" aria-labelledby="service-directory-title" data-testid="service-directory">
            <div class="et-container">
                <div class="et-v3-section-head">
                    <div>
                        <p class="et-eyebrow">{{ __('pro.services.directory_kicker') }}</p>
                        <h2 class="et-h2" id="service-directory-title">{{ __('pro.services.directory_title') }}</h2>
                        <p class="et-lead">{{ __('pro.services.directory_text') }}</p>
                    </div>
                </div>
                <div class="et-service-directory__grid">
                    @foreach ($serviceDirectory as $entry)
                        <article class="et-service-directory__item" id="{{ $entry['id'] }}">
                            <span aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3>{{ $entry['title'] }}</h3>
                            <p>{{ $entry['text'] }}</p>
                            <a class="et-action-link" href="{{ $entry['href'] }}"><span class="et-action-link__label">{{ __('pro.services.directory_contact') }}</span><x-icon name="arrow" :size="16" /></a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="et-v3-section et-v3-section--ink">
        <div class="et-container et-v3-services-cta">
            <div class="et-v3-services-cta__content">
                <p class="et-eyebrow">{{ __('pro.services.request_kicker') }}</p>
                <h2 class="et-h2">{{ __('pro.services.request_title') }}</h2>
                <p class="et-lead">{{ __('pro.services.request_text') }}</p>
                <a class="et-btn et-btn--primary" href="tel:{{ $contact['phone_href'] }}">{{ __('services.call_cta') }} {{ $contact['phone_display'] }} <x-icon name="phone" :size="18" /></a>
            </div>
            <div class="et-v3-services-cta__media">
                <img src="/brand/workshop-alt.webp" width="1400" height="900" loading="lazy" alt="{{ __('pro.content.workshop_image_alt') }}">
            </div>
        </div>
    </section>
</x-layouts.storefront>
