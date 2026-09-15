@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    $isCartEmpty = $cart->lines === [] && $cart->issues === [] && $cart->serviceIssues === [];
@endphp

<x-layouts.storefront :title="__('commerce.cart')" active-section="cart">
    <section class="et-page-hero et-page-hero--light et-page-hero--transactional">
        <div class="et-container et-page-hero__inner">
            <div class="et-page-hero__content">
                <div class="et-breadcrumbs"><span><a href="{{ route($prefix.'home') }}">{{ __('pro.content.back_home') }}</a></span><span>{{ __('commerce.cart') }}</span></div>
                <p class="et-eyebrow">{{ __('pro.nav.store') }}</p>
                <h1 class="et-h1">{{ __('commerce.cart') }}</h1>
                <p class="et-lead">{{ __('commerce.pickup') }}</p>
            </div>
        </div>
    </section>

    <section class="et-section et-section--compact" aria-live="polite">
        <div class="et-container">
            @unless ($isCartEmpty)
                <div class="et-toolbar et-cart-toolbar">
                    <div></div>
                    <a class="et-btn et-btn--outline" href="{{ route($prefix.'tires.index') }}">{{ __('commerce.continue_shopping') }}</a>
                </div>
            @endunless

            @if (session('cart_error'))
                <div class="et-alert et-alert--error" role="alert"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ session('cart_error') }}</div></div>
            @endif

            @if ($isCartEmpty)
                <div class="et-cart-empty" data-testid="cart-empty">
                    <div class="et-cart-empty__mark" aria-hidden="true"><x-icon name="cart" :size="34" /></div>
                    <div class="et-cart-empty__copy">
                        <p class="et-eyebrow">{{ __('pro.nav.store') }}</p>
                        <h2 class="et-h2">{{ __('commerce.empty') }}</h2>
                        <p>{{ __('commerce.empty_help') }}</p>
                    </div>
                    <a class="et-btn et-btn--primary" href="{{ route($prefix.'tires.index') }}">{{ __('commerce.continue_shopping') }}</a>
                </div>
            @else
                <div class="et-cart-layout et-cart-layout--populated">
                    <div class="et-cart-list">
                        @foreach ($cart->lines as $line)
                            @php
                                $quantityId = 'cart-quantity-'.$loop->index;
                                $linePresenter = new \App\Modules\Catalog\Presentation\CatalogItemPresenter($line->item);
                                $lineTitle = filled($line->item->modelName) ? trim($line->item->brand.' '.$line->item->modelName) : $line->item->title;
                            @endphp
                            <article class="et-cart-item" data-testid="cart-line" data-product-sku="{{ $line->item->sku }}">
                                <div class="et-cart-item__media" data-testid="cart-line-media">
                                    @include('storefront.catalog._product-visual', ['item' => $line->item, 'attributes' => new \Illuminate\View\ComponentAttributeBag(['loading' => 'lazy', 'class' => 'size-full object-contain'])])
                                </div>
                                <div>
                                    <h2>{{ $lineTitle }}</h2>
                                    @if ($line->item->isIntegrationTest)<span class="et-badge et-badge--red">{{ __('storefront.integration_test_product') }}</span>@endif
                                    <p>{{ $linePresenter->dimension() }} · {{ __('commerce.sku') }}: {{ $line->item->sku }}</p>
                                    <div class="et-cart-item__controls">
                                        <form class="et-cluster" method="post" action="{{ route($prefix.'cart.update') }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="sku" value="{{ $line->item->sku }}">
                                            <div>
                                                <label class="et-label" for="{{ $quantityId }}">{{ __('commerce.quantity') }}</label>
                                                @php $requiredTestQuantity = (int) config('ndi_api.sandbox.quantity', 2); @endphp
                                                <input class="et-input easytire-input et-cart-quantity" id="{{ $quantityId }}" name="quantity" data-testid="cart-line-quantity" type="number" min="{{ $line->item->isIntegrationTest ? $requiredTestQuantity : 1 }}" max="{{ $line->item->isIntegrationTest ? $requiredTestQuantity : min(40, $line->item->stock->quantity) }}" value="{{ $line->quantity }}" inputmode="numeric" {{ $line->item->isIntegrationTest ? 'readonly' : '' }}>
                                            </div>
                                            <button class="et-btn et-btn--dark et-btn--small et-cart-action" type="submit" data-testid="cart-line-update">{{ __('commerce.update') }}</button>
                                        </form>
                                        <form class="et-cart-action" method="post" action="{{ route($prefix.'cart.remove') }}">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="sku" value="{{ $line->item->sku }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <button class="et-btn et-btn--link et-btn--small" type="submit" data-testid="cart-line-remove">{{ __('commerce.remove') }}</button>
                                        </form>
                                    </div>
                                </div>
                                <dl class="et-cart-item__price">
                                    <div><dt class="et-caption">{{ __('commerce.unit_price') }}</dt><dd><x-storefront-money :minor="$line->price->money->minor" :currency="$line->price->money->currency" /></dd></div>
                                    <div class="et-cart-line-total"><dt class="et-caption">{{ __('commerce.line_total') }}</dt><dd class="et-price"><x-storefront-money :minor="$line->total()->minor" :currency="$line->total()->currency" /></dd></div>
                                </dl>
                            </article>
                        @endforeach

                        @foreach ($cart->issues as $issue)
                            @php($issueQuantityId = 'cart-issue-quantity-'.$loop->index)
                            <article class="et-alert et-alert--error" data-testid="cart-issue">
                                <span class="et-alert__icon" aria-hidden="true">!</span>
                                <div>
                                    <h2 class="et-h3">{{ __('commerce.issue_title') }}</h2>
                                    <p>{{ __('commerce.issue_'.$issue->reason, ['sku' => $issue->sku, 'quantity' => $issue->quantity, 'available' => $issue->availableQuantity ?? 0]) }}</p>
                                    <div class="et-cluster et-cart-issue-actions">
                                        @if (in_array($issue->reason, ['stock_changed', 'sandbox_quantity_required'], true) && $issue->availableQuantity > 0)
                                            <form class="et-cluster" method="post" action="{{ route($prefix.'cart.update') }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="sku" value="{{ $issue->sku }}">
                                                <div><label class="et-label" for="{{ $issueQuantityId }}">{{ __('commerce.quantity') }}</label><input class="et-input et-cart-issue-quantity" id="{{ $issueQuantityId }}" name="quantity" type="number" min="{{ $issue->reason === 'sandbox_quantity_required' ? $issue->availableQuantity : 1 }}" max="{{ min(40, $issue->availableQuantity) }}" value="{{ $issue->reason === 'sandbox_quantity_required' ? $issue->availableQuantity : min($issue->quantity, $issue->availableQuantity) }}" inputmode="numeric" {{ $issue->reason === 'sandbox_quantity_required' ? 'readonly' : '' }}></div>
                                                <button class="et-btn et-btn--dark et-btn--small" type="submit">{{ __('commerce.update') }}</button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route($prefix.'cart.remove') }}">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="sku" value="{{ $issue->sku }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <button class="et-btn et-btn--outline et-btn--small" type="submit">{{ __('commerce.remove') }}</button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        @endforeach

                        @if ($cart->serviceOptions !== [])
                            <section class="et-card" data-testid="cart-service-addons">
                                <div class="et-card__body">
                                    <p class="et-eyebrow">{{ __('commerce.additional_services') }}</p>
                                    <h2 class="et-h3 et-cart-addons-title">{{ __('commerce.additional_services') }}</h2>
                                    <p class="et-copy">{{ __('commerce.additional_services_intro') }}</p>
                                    <div class="et-service-addon-list">
                                        @foreach ($cart->serviceOptions as $option)
                                            <article class="et-addon {{ $option->selected ? 'et-addon--selected' : '' }}" data-testid="service-addon" data-service-code="{{ $option->code }}">
                                                <div>
                                                    <div class="et-cluster">
                                                        <strong>{{ $option->title(app()->getLocale()) }}</strong>
                                                        @if ($option->selected)<span class="et-badge et-badge--red">{{ __('commerce.service_selected') }}</span>@endif
                                                    </div>
                                                    <small>{{ __('commerce.service_'.$option->billingMode->value) }} · {{ __('commerce.quantity') }}: {{ $option->quantity }}</small>
                                                    <div class="et-price et-cart-addon-price"><x-storefront-money :minor="$option->total()->minor" :currency="$option->total()->currency" /></div>
                                                </div>
                                                <form method="post" action="{{ route($prefix.($option->selected ? 'cart.services.remove' : 'cart.services.add')) }}">
                                                    @csrf
                                                    @if ($option->selected) @method('DELETE') @endif
                                                    <input type="hidden" name="code" value="{{ $option->code }}">
                                                    <button class="et-btn {{ $option->selected ? 'et-btn--outline' : 'et-btn--dark' }} et-btn--small" type="submit" data-testid="service-addon-toggle">{{ $option->selected ? __('commerce.remove_service') : __('commerce.add_service') }}</button>
                                                </form>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endif

                        @foreach ($cart->serviceIssues as $issue)
                            <article class="et-alert et-alert--error" data-testid="cart-service-issue" data-service-code="{{ $issue->code }}">
                                <span class="et-alert__icon" aria-hidden="true">!</span>
                                <div>
                                    <h2 class="et-h3">{{ __('commerce.service_issue_title') }}</h2>
                                    <p>{{ __('commerce.service_issue_'.$issue->reason, ['code' => $issue->code]) }}</p>
                                    <form class="et-cart-issue-actions" method="post" action="{{ route($prefix.'cart.services.remove') }}">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="code" value="{{ $issue->code }}">
                                        <button class="et-btn et-btn--outline et-btn--small" type="submit">{{ __('commerce.remove_service') }}</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <aside class="et-order-summary">
                        <p class="et-eyebrow">{{ __('commerce.cart_total') }}</p>
                        <dl>
                            <div class="et-order-summary__row"><dt>{{ __('commerce.products_total') }}</dt><dd><strong><x-storefront-money :minor="$cart->productsTotal()->minor" :currency="$cart->productsTotal()->currency" /></strong></dd></div>
                            @if ($cart->servicesTotal()->minor > 0)<div class="et-order-summary__row"><dt>{{ __('commerce.services_total') }}</dt><dd><strong><x-storefront-money :minor="$cart->servicesTotal()->minor" :currency="$cart->servicesTotal()->currency" /></strong></dd></div>@endif
                        </dl>
                        <div class="et-order-summary__total"><span>{{ __('commerce.cart_total') }}</span><span><x-storefront-money :minor="$cart->total->minor" :currency="$cart->total->currency" /></span></div>
                        @if ($cart->issues !== [] || $cart->serviceIssues !== [])
                            <div class="et-alert et-alert--warning et-cart-summary-action" role="alert"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ __('commerce.issues') }}</div></div>
                        @else
                            <a class="et-btn et-btn--primary et-btn--full et-cart-summary-action" href="{{ route($prefix.'checkout.create') }}" data-testid="cart-checkout">{{ __('commerce.to_checkout') }}</a>
                        @endif
                    </aside>
                </div>
            @endif
        </div>
    </section>
</x-layouts.storefront>
