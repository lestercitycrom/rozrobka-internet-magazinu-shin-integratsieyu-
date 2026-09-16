@if ($cards->isNotEmpty())
    <section class="et-v3-section et-v3-section--services et-home-service-collection" aria-labelledby="{{ $headingId }}" data-testid="{{ $testId }}" data-service-collection="{{ $collectionKey }}">
        <div class="et-container">
            <div class="et-v3-section-head">
                <div>
                    <p class="et-eyebrow">{{ $kicker }}</p>
                    <h2 class="et-h2" id="{{ $headingId }}">{{ $heading }}</h2>
                    <p class="et-lead">{{ $description }}</p>
                </div>
                <a class="et-action-link" href="{{ route($routePrefix.'services.index') }}"><span class="et-action-link__label">{{ __('pro.home.services_all') }}</span><x-icon name="arrow" :size="16" /></a>
            </div>

            <div class="et-v3-service-grid">
                @foreach ($cards as $card)
                    @php
                        $featured = $loop->index < 2;
                        $media = $featured ? $serviceCardMedia($card) : null;
                        $destination = $serviceCardUrl($card);
                        $external = \App\Support\PublicEditorialUrl::isExternal($destination);
                        $title = $card->getAttribute('title_'.$homepageLocale);
                        $cardDescription = $card->getAttribute('description_'.$homepageLocale);
                        $cta = $card->getAttribute('cta_label_'.$homepageLocale);
                    @endphp
                    <article @class(['et-v3-service', 'et-v3-service--feature' => $featured]) data-testid="homepage-service-card" data-card-key="{{ $card->content_key ?: $card->id }}">
                        @if ($featured)
                            @if ($media)
                                <img src="{{ $media['url'] }}" width="1200" height="900" loading="lazy" alt="{{ $media['alt'] }}">
                            @else
                                <div class="et-v3-service__fallback" data-testid="homepage-service-media-fallback"><img src="/brand/easytire-mark.svg" width="96" height="96" alt=""><span>{{ __('pro.services.media_unavailable') }}</span></div>
                            @endif
                        @endif
                        <div class="et-v3-service__content">
                            <h3>{{ $title }}</h3>
                            <p>{{ $cardDescription }}</p>
                            <a class="et-action-link" href="{{ $destination }}" @if($external) target="_blank" rel="noopener" @endif aria-label="{{ $cta }}: {{ $title }}"><span class="et-action-link__label">{{ $cta }}</span><x-icon name="arrow" :size="16" /></a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
