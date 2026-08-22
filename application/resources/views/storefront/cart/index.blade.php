@php
    $prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
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
            <div class="et-toolbar">
                <div></div>
                <a class="et-btn et-btn--outline" href="{{ route($prefix.'tires.index') }}">{{ __('commerce.continue_shopping') }}</a>
            </div>

            @if (session('cart_error'))
                <div class="et-alert et-alert--error" role="alert"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ session('cart_error') }}</div></div>
            @endif

            @if ($cart->lines === [] && $cart->issues === [] && $cart->serviceIssues === [])
                <div class="et-empty-state border-dashed" style="margin-top:1rem" data-testid="cart-empty">
                    <div>
                        <div class="et-empty-state__icon" aria-hidden="true"><x-icon name="cart" :size="34" /></div>
                        <h2 class="et-h3" style="margin-top:1rem">{{ __('commerce.empty') }}</h2>
                        <a class="et-btn et-btn--primary" href="{{ route($prefix.'tires.index') }}" style="margin-top:1rem">{{ __('commerce.continue_shopping') }}</a>
                    </div>
                </div>
            @else
                <div class="et-cart-layout" style="margin-top:1rem">
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
                                                <input class="et-input easytire-input" id="{{ $quantityId }}" name="quantity" data-testid="cart-line-quantity" type="number" min="{{ $line->item->isIntegrationTest ? $requiredTestQuantity : 1 }}" max="{{ $line->item->isIntegrationTest ? $requiredTestQuantity : min(40, $line->item->stock->quantity) }}" value="{{ $line->quantity }}" inputmode="numeric" style="width:88px;min-height:40px" {{ $line->item->isIntegrationTest ? 'readonly' : '' }}>
                                            </div>
                                            <button class="et-btn et-btn--dark et-btn--small" type="submit" style="align-self:end" data-testid="cart-line-update">{{ __('commerce.update') }}</button>
                                        </form>
                                        <form method="post" action="{{ route($prefix.'cart.remove') }}" style="align-self:end">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="sku" value="{{ $line->item->sku }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <button class="et-btn et-btn--link et-btn--small" type="submit" data-testid="cart-line-remove">{{ __('commerce.remove') }}</button>
                                        </form>
                                    </div>
                                </div>
                                <dl class="et-cart-item__price">
                                    <div><dt class="et-caption">{{ __('commerce.unit_price') }}</dt><dd>{{ number_format($line->price->money->minor / 100, 2, ',', ' ') }} {{ $line->price->money->currency }}</dd></div>
                                    <div style="margin-top:.6rem"><dt class="et-caption">{{ __('commerce.line_total') }}</dt><dd class="et-price">{{ number_format($line->total()->minor / 100, 2, ',', ' ') }} {{ $line->total()->currency }}</dd></div>
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
                                    <div class="et-cluster" style="margin-top:.8rem">
                                        @if (in_array($issue->reason, ['stock_changed', 'sandbox_quantity_required'], true) && $issue->availableQuantity > 0)
                                            <form class="et-cluster" method="post" action="{{ route($prefix.'cart.update') }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="sku" value="{{ $issue->sku }}">
                                                <div><label class="et-label" for="{{ $issueQuantityId }}">{{ __('commerce.quantity') }}</label><input class="et-input" id="{{ $issueQuantityId }}" name="quantity" type="number" min="{{ $issue->reason === 'sandbox_quantity_required' ? $issue->availableQuantity : 1 }}" max="{{ min(40, $issue->availableQuantity) }}" value="{{ $issue->reason === 'sandbox_quantity_required' ? $issue->availableQuantity : min($issue->quantity, $issue->availableQuantity) }}" inputmode="numeric" style="width:88px" {{ $issue->reason === 'sandbox_quantity_required' ? 'readonly' : '' }}></div>
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
                                    <h2 class="et-h3" style="margin-top:.6rem">{{ __('commerce.additional_services') }}</h2>
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
                                                    <div class="et-price" style="margin-top:.45rem">{{ number_format($option->total()->minor / 100, 2, ',', ' ') }} {{ $option->total()->currency }}</div>
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
                                    <form method="post" action="{{ route($prefix.'cart.services.remove') }}" style="margin-top:.8rem">
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
                            <div class="et-order-summary__row"><dt>{{ __('commerce.products_total') }}</dt><dd><strong>{{ number_format($cart->productsTotal()->minor / 100, 2, ',', ' ') }} {{ $cart->productsTotal()->currency }}</strong></dd></div>
                            @if ($cart->servicesTotal()->minor > 0)<div class="et-order-summary__row"><dt>{{ __('commerce.services_total') }}</dt><dd><strong>{{ number_format($cart->servicesTotal()->minor / 100, 2, ',', ' ') }} {{ $cart->servicesTotal()->currency }}</strong></dd></div>@endif
                        </dl>
                        <div class="et-order-summary__total"><span>{{ __('commerce.cart_total') }}</span><span>{{ number_format($cart->total->minor / 100, 2, ',', ' ') }} {{ $cart->total->currency }}</span></div>
                        @if ($cart->issues !== [] || $cart->serviceIssues !== [])
                            <div class="et-alert et-alert--warning" style="margin-top:1rem" role="alert"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ __('commerce.issues') }}</div></div>
                        @else
                            <a class="et-btn et-btn--primary et-btn--full" href="{{ route($prefix.'checkout.create') }}" style="margin-top:1rem" data-testid="cart-checkout">{{ __('commerce.to_checkout') }}</a>
                        @endif
                    </aside>
                </div>
            @endif
        </div>
    </section>
</x-layouts.storefront>
