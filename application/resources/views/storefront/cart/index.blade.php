@php($prefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.')

<x-layouts.storefront :title="__('commerce.cart')">
    <section class="mx-auto max-w-5xl px-5 py-12" aria-live="polite">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-easytire-brand">EasyTire</p>
                <h1 class="mt-2 text-3xl font-black">{{ __('commerce.cart') }}</h1>
            </div>
            <a class="inline-flex min-h-11 items-center rounded-full border border-easytire-border px-5 font-bold" href="{{ route($prefix.'tires.index') }}">
                {{ __('commerce.continue_shopping') }}
            </a>
        </div>

        @if ($cart->lines === [])
            <div class="mt-8 rounded-2xl border border-easytire-border bg-white p-6">
                <p>{{ __('commerce.empty') }}</p>
            </div>
        @else
            <div class="mt-8 space-y-4">
                @foreach ($cart->lines as $line)
                    @php($quantityId = 'cart-quantity-'.$loop->index)
                    <article class="rounded-2xl border border-easytire-border bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-black">{{ $line->item->title }}</h2>
                                <p class="mt-1 text-sm text-easytire-muted">{{ __('commerce.sku') }}: {{ $line->item->sku }}</p>
                            </div>
                            <dl class="text-right text-sm">
                                <div>
                                    <dt class="inline text-easytire-muted">{{ __('commerce.unit_price') }}:</dt>
                                    <dd class="inline font-bold">{{ number_format($line->price->money->minor / 100, 2, ',', ' ') }} {{ $line->price->money->currency }}</dd>
                                </div>
                                <div class="mt-1">
                                    <dt class="inline text-easytire-muted">{{ __('commerce.line_total') }}:</dt>
                                    <dd class="inline font-black">{{ number_format($line->total()->minor / 100, 2, ',', ' ') }} {{ $line->total()->currency }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="mt-4 flex flex-wrap items-end gap-3">
                            <form class="flex flex-wrap items-end gap-2" method="post" action="{{ route($prefix.'cart.update') }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="sku" value="{{ $line->item->sku }}">
                                <div>
                                    <label class="block text-sm font-bold" for="{{ $quantityId }}">{{ __('commerce.quantity') }}</label>
                                    <input id="{{ $quantityId }}" class="mt-1 min-h-11 w-24 rounded-lg border border-easytire-border px-3" name="quantity" type="number" min="1" max="40" value="{{ $line->quantity }}" inputmode="numeric">
                                </div>
                                <button class="min-h-11 rounded-lg bg-easytire-ink px-4 font-bold text-white" type="submit">{{ __('commerce.update') }}</button>
                            </form>

                            <form method="post" action="{{ route($prefix.'cart.remove') }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="sku" value="{{ $line->item->sku }}">
                                <input type="hidden" name="quantity" value="1">
                                <button class="min-h-11 rounded-lg border border-easytire-border px-4 font-bold" type="submit">{{ __('commerce.remove') }}</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-6 rounded-2xl bg-easytire-ink p-6 text-white">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-white/70">{{ __('commerce.cart_total') }}</p>
                <p class="mt-2 text-2xl font-black">{{ number_format($cart->total->minor / 100, 2, ',', ' ') }} {{ $cart->total->currency }}</p>

                @if ($cart->issues !== [])
                    <p class="mt-4 rounded-lg bg-white/10 p-3" role="alert">{{ __('commerce.issues') }}</p>
                @else
                    <a class="mt-5 inline-flex min-h-11 items-center rounded-full bg-easytire-brand px-5 font-bold text-white" href="{{ route($prefix.'checkout.create') }}">
                        {{ __('commerce.to_checkout') }}
                    </a>
                @endif
            </div>
        @endif
    </section>
</x-layouts.storefront>
