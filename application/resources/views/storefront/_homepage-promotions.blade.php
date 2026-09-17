<span class="et-section-anchor" id="aktuelle-tilbud" aria-hidden="true"></span>
<section class="et-v3-notices" aria-labelledby="home-promotions-title" data-testid="homepage-promotions">
    <div class="et-container">
        <div class="et-v3-section-head et-v3-section-head--compact">
            <div>
                <p class="et-eyebrow">{{ __('pro.home.promotions_kicker') }}</p>
                <h2 class="et-h2" id="home-promotions-title">{{ __('pro.home.promotions_title') }}</h2>
            </div>
            @if ($homepagePromotions->count() > 1)
                <div class="et-promotion-carousel__controls" hidden data-promotion-controls>
                    <button type="button" data-promotion-previous aria-label="{{ __('pro.home.promotion_previous') }}"><x-icon name="arrow" :size="20" /></button>
                    <button type="button" data-promotion-next aria-label="{{ __('pro.home.promotion_next') }}"><x-icon name="arrow" :size="20" /></button>
                </div>
            @endif
        </div>

        @if ($homepagePromotions->isEmpty())
            <div class="et-v3-promotions-empty" data-testid="homepage-promotions-empty">
                <p>{{ __('pro.home.promotions_empty') }}</p>
                <a class="et-action-link" href="{{ route($routePrefix.'services.index') }}"><span class="et-action-link__label">{{ __('pro.home.services_all') }}</span><x-icon name="arrow" :size="16" /></a>
            </div>
        @else
            <div class="et-promotion-carousel" data-promotion-carousel data-total="{{ $homepagePromotions->count() }}">
                <div class="et-promotion-carousel__viewport" data-promotion-viewport>
                    <div class="et-v3-promotion-grid" data-promotion-track>
                        @foreach ($homepagePromotions as $promotion)
                            @php
                                $title = $promotion->getAttribute('title_'.$homepageLocale);
                                $storedShortBody = $promotion->getAttribute('short_body_'.$homepageLocale);
                                $body = $promotion->getAttribute('body_'.$homepageLocale);
                                $hasSeparateDetails = filled($storedShortBody);
                                $ctaLabel = $promotion->getAttribute('cta_label_'.$homepageLocale);
                                $ctaUrl = $promotionUrl($promotion);
                                $externalPromotion = \App\Support\PublicEditorialUrl::isExternal($ctaUrl);
                                $promotionAsset = $promotion->mediaAsset;
                                $promotionImage = $promotionAsset
                                    ? \Illuminate\Support\Facades\Storage::disk($promotionAsset->disk)->url($promotionAsset->path)
                                    : null;
                                $promotionAlt = $promotionAsset?->getAttribute('alt_'.$homepageLocale) ?: '';
                                @endphp
                            @php($detailsId = 'promotion-details-'.$promotion->id)
                            @php($titleId = 'promotion-title-'.$promotion->id)
                            <article class="et-v3-promotion" data-testid="homepage-promotion" data-card-overlay-root data-card-overlay-kind="promotion">
                                <div class="et-v3-promotion__fallback" aria-hidden="true"><img src="/brand/easytire-mark.svg" width="100" height="100" alt=""></div>
                                @if ($promotionImage)
                                    <img src="{{ $promotionImage }}" width="1200" height="900" loading="lazy" alt="{{ $promotionAlt }}" data-media-fallback-image>
                                @endif
                                <span class="et-v3-promotion__shade" aria-hidden="true"></span>
                                <div class="et-v3-promotion__content">
                                    @if (config('storefront.design_profile') === 'classic')
                                        <span class="et-v3-promotion__number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    @endif
                                    <h3 id="{{ $titleId }}">{{ $title }}</h3>
                                    @if ($hasSeparateDetails)
                                        <p class="et-v3-promotion__short">{{ $storedShortBody }}</p>
                                    @else
                                        <p class="et-v3-promotion__short">{{ \Illuminate\Support\Str::limit($body, 100) }}</p>
                                    @endif
                                    <button class="et-v3-promotion__disclosure et-action-link" type="button" hidden aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $detailsId }}" data-card-overlay-open data-promotion-card-overlay-open><span class="et-action-link__label">{{ __('pro.home.promotion_details') }}</span><x-icon name="arrow" :size="16" /></button>
                                    <details data-card-overlay-fallback><summary>{{ __('pro.home.promotion_details') }}</summary><p>{{ $body }}</p></details>
                                    <a class="et-v3-promotion__cta et-action-link" href="{{ $ctaUrl }}" @if($externalPromotion) target="_blank" rel="noopener" @endif><span class="et-action-link__label">{{ $ctaLabel }}</span><x-icon name="arrow" :size="16" /></a>
                                </div>
                                <dialog class="et-card-overlay" id="{{ $detailsId }}" aria-labelledby="{{ $detailsId }}-title" data-card-overlay data-promotion-card-overlay>
                                    <div class="et-card-overlay__surface">
                                        <button class="et-card-overlay__close" type="button" aria-label="{{ __('project.home.dialog_close') }}" data-card-overlay-close>×</button>
                                        <p class="et-eyebrow">{{ __('pro.home.promotion_details') }}</p>
                                        <h2 id="{{ $detailsId }}-title">{{ $title }}</h2>
                                        <p>{{ $body }}</p>
                                        <a class="et-btn et-btn--primary" href="{{ $ctaUrl }}" @if($externalPromotion) target="_blank" rel="noopener" @endif>{{ $ctaLabel }} <x-icon name="arrow" :size="18" /></a>
                                    </div>
                                </dialog>
                            </article>
                        @endforeach
                    </div>
                </div>
                @if ($homepagePromotions->count() > 1)
                    <p class="sr-only" aria-live="polite" data-promotion-status data-status-template="{{ __('pro.home.promotion_status', ['current' => '__CURRENT__', 'total' => '__TOTAL__']) }}"></p>
                @endif
            </div>
        @endif
    </div>
</section>
