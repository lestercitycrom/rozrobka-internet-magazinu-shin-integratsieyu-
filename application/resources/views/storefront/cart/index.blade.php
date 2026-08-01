@php($prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.')
<x-layouts.storefront :title="__('commerce.cart')">
    <section class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14" aria-live="polite">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-easytire-brand">EasyTire</p><h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ __('commerce.cart') }}</h1></div>
            <a class="inline-flex min-h-11 items-center rounded-full border border-easytire-border bg-white px-5 font-bold transition hover:border-easytire-brand hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" href="{{ route($prefix.'tires.index') }}">{{ __('commerce.continue_shopping') }}</a>
        </div>

        @if (session('cart_error'))
            <p class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800" role="alert">{{ session('cart_error') }}</p>
        @endif

        @if ($cart->lines === [] && $cart->issues === [] && $cart->serviceIssues === [])
            <div class="mt-8 rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center">
                <p class="text-lg font-bold">{{ __('commerce.empty') }}</p>
                <a class="mt-5 inline-flex min-h-11 items-center font-bold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route($prefix.'tires.index') }}">{{ __('commerce.continue_shopping') }}</a>
            </div>
        @else
            <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
                <div class="space-y-4">
                    @foreach ($cart->lines as $line)
                        @php($quantityId = 'cart-quantity-'.$loop->index)
                        <article class="easytire-card rounded-2xl p-5 sm:p-6">
                            <div class="flex flex-wrap justify-between gap-4">
                                <div><h2 class="text-lg font-black">{{ $line->item->title }}</h2><p class="mt-1 text-sm text-easytire-muted">{{ __('commerce.sku') }}: {{ $line->item->sku }}</p></div>
                                <dl class="rounded-xl bg-easytire-surface px-4 py-3 text-right text-sm"><div><dt class="inline text-easytire-muted">{{ __('commerce.unit_price') }}:</dt><dd class="inline font-bold">{{ number_format($line->price->money->minor / 100, 2, ',', ' ') }} {{ $line->price->money->currency }}</dd></div><div class="mt-1"><dt class="inline text-easytire-muted">{{ __('commerce.line_total') }}:</dt><dd class="inline font-black">{{ number_format($line->total()->minor / 100, 2, ',', ' ') }} {{ $line->total()->currency }}</dd></div></dl>
                            </div>
                            <div class="mt-5 flex flex-wrap items-end gap-3 border-t border-easytire-border pt-5">
                                <form class="flex flex-wrap items-end gap-3" method="post" action="{{ route($prefix.'cart.update') }}">@csrf @method('PATCH')<input type="hidden" name="sku" value="{{ $line->item->sku }}"><div><label class="block text-sm font-bold" for="{{ $quantityId }}">{{ __('commerce.quantity') }}</label><input id="{{ $quantityId }}" class="easytire-input mt-2 min-h-11 w-24 rounded-lg px-3" name="quantity" type="number" min="1" max="{{ min(40, $line->item->stock->quantity) }}" value="{{ $line->quantity }}" inputmode="numeric"></div><button class="min-h-11 rounded-lg bg-easytire-ink px-4 font-bold text-white transition hover:bg-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" type="submit">{{ __('commerce.update') }}</button></form>
                                <form method="post" action="{{ route($prefix.'cart.remove') }}">@csrf @method('DELETE')<input type="hidden" name="sku" value="{{ $line->item->sku }}"><input type="hidden" name="quantity" value="1"><button class="min-h-11 rounded-lg border border-easytire-border bg-white px-4 font-bold transition hover:border-easytire-brand hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" type="submit">{{ __('commerce.remove') }}</button></form>
                            </div>
                        </article>
                    @endforeach

                    @foreach ($cart->issues as $issue)
                        @php($issueQuantityId = 'cart-issue-quantity-'.$loop->index)
                        <article class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6" data-testid="cart-issue">
                            <h2 class="font-black text-red-900">{{ __('commerce.issue_title') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-red-800">{{ __('commerce.issue_'.$issue->reason, ['sku' => $issue->sku, 'quantity' => $issue->quantity, 'available' => $issue->availableQuantity ?? 0]) }}</p>
                            <div class="mt-4 flex flex-wrap items-end gap-3">
                                @if ($issue->reason === 'stock_changed' && $issue->availableQuantity > 0)
                                    <form class="flex flex-wrap items-end gap-3" method="post" action="{{ route($prefix.'cart.update') }}">@csrf @method('PATCH')<input type="hidden" name="sku" value="{{ $issue->sku }}"><div><label class="block text-sm font-bold" for="{{ $issueQuantityId }}">{{ __('commerce.quantity') }}</label><input id="{{ $issueQuantityId }}" class="easytire-input mt-2 min-h-11 w-24 rounded-lg bg-white px-3" name="quantity" type="number" min="1" max="{{ min(40, $issue->availableQuantity) }}" value="{{ min($issue->quantity, $issue->availableQuantity) }}" inputmode="numeric"></div><button class="min-h-11 rounded-lg bg-easytire-ink px-4 font-bold text-white" type="submit">{{ __('commerce.update') }}</button></form>
                                @endif
                                <form method="post" action="{{ route($prefix.'cart.remove') }}">@csrf @method('DELETE')<input type="hidden" name="sku" value="{{ $issue->sku }}"><input type="hidden" name="quantity" value="1"><button class="min-h-11 rounded-lg border border-red-300 bg-white px-4 font-bold text-red-800" type="submit">{{ __('commerce.remove') }}</button></form>
                            </div>
                        </article>
                    @endforeach

                    @if ($cart->serviceOptions !== [])
                        <section class="rounded-2xl border border-easytire-border bg-white p-5 sm:p-6" data-testid="cart-service-addons">
                            <h2 class="text-xl font-black">{{ __('commerce.additional_services') }}</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-easytire-muted">{{ __('commerce.additional_services_intro') }}</p>
                            <div class="mt-5 grid gap-3">
                                @foreach ($cart->serviceOptions as $option)
                                    <article class="flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between {{ $option->selected ? 'border-easytire-brand bg-easytire-brand-soft' : 'border-easytire-border bg-easytire-surface' }}" data-testid="service-addon" data-service-code="{{ $option->code }}">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="font-black">{{ $option->title(app()->getLocale()) }}</h3>
                                                @if ($option->selected)<span class="rounded-full bg-easytire-brand px-2.5 py-1 text-xs font-bold text-white">{{ __('commerce.service_selected') }}</span>@endif
                                            </div>
                                            <p class="mt-1 text-sm text-easytire-muted">{{ __('commerce.service_'.$option->billingMode->value) }} · {{ __('commerce.quantity') }}: {{ $option->quantity }}</p>
                                            <p class="mt-2 font-black">{{ number_format($option->total()->minor / 100, 2, ',', ' ') }} {{ $option->total()->currency }}</p>
                                        </div>
                                        <form method="post" action="{{ route($prefix.($option->selected ? 'cart.services.remove' : 'cart.services.add')) }}">
                                            @csrf
                                            @if ($option->selected) @method('DELETE') @endif
                                            <input type="hidden" name="code" value="{{ $option->code }}">
                                            <button class="min-h-11 rounded-lg px-4 font-bold transition-colors focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand {{ $option->selected ? 'border border-easytire-border bg-white text-easytire-ink hover:border-easytire-brand' : 'bg-easytire-ink text-white hover:bg-easytire-brand' }}" type="submit" data-testid="service-addon-toggle">
                                                {{ $option->selected ? __('commerce.remove_service') : __('commerce.add_service') }}
                                            </button>
                                        </form>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @foreach ($cart->serviceIssues as $issue)
                        <article class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6" data-testid="cart-service-issue" data-service-code="{{ $issue->code }}">
                            <h2 class="font-black text-red-900">{{ __('commerce.service_issue_title') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-red-800">{{ __('commerce.service_issue_'.$issue->reason, ['code' => $issue->code]) }}</p>
                            <form class="mt-4" method="post" action="{{ route($prefix.'cart.services.remove') }}">
                                @csrf @method('DELETE')
                                <input type="hidden" name="code" value="{{ $issue->code }}">
                                <button class="min-h-11 rounded-lg border border-red-300 bg-white px-4 font-bold text-red-800 transition-colors hover:border-red-500" type="submit">{{ __('commerce.remove_service') }}</button>
                            </form>
                        </article>
                    @endforeach
                </div>

                <aside class="rounded-2xl bg-easytire-ink p-6 text-white lg:sticky lg:top-24">
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-white/70">{{ __('commerce.cart_total') }}</p>
                    <dl class="mt-5 space-y-2 border-y border-white/15 py-4 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-white/70">{{ __('commerce.products_total') }}</dt><dd class="font-bold">{{ number_format($cart->productsTotal()->minor / 100, 2, ',', ' ') }} {{ $cart->productsTotal()->currency }}</dd></div>
                        @if ($cart->servicesTotal()->minor > 0)<div class="flex justify-between gap-4"><dt class="text-white/70">{{ __('commerce.services_total') }}</dt><dd class="font-bold">{{ number_format($cart->servicesTotal()->minor / 100, 2, ',', ' ') }} {{ $cart->servicesTotal()->currency }}</dd></div>@endif
                    </dl>
                    <p class="mt-2 text-3xl font-black">{{ number_format($cart->total->minor / 100, 2, ',', ' ') }} {{ $cart->total->currency }}</p>
                    @if ($cart->issues !== [] || $cart->serviceIssues !== [])<p class="mt-5 rounded-xl bg-white/10 p-4 text-sm leading-6" role="alert">{{ __('commerce.issues') }}</p>@else<a class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-easytire-brand px-5 font-bold text-white shadow-sm transition hover:bg-red-600 focus:outline-2 focus:outline-offset-4 focus:outline-white" href="{{ route($prefix.'checkout.create') }}">{{ __('commerce.to_checkout') }}</a>@endif
                </aside>
            </div>
        @endif
    </section>
</x-layouts.storefront>
