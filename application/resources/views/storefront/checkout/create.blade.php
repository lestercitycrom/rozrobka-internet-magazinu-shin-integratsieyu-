<x-layouts.storefront :title="__('commerce.checkout')">
    <section class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
            <form method="post" class="easytire-card space-y-6 rounded-2xl p-5 sm:p-8">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <input name="website" class="hidden" aria-hidden="true" tabindex="-1" autocomplete="off">

                <header>
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('commerce.checkout') }}</h1>
                    <p class="mt-3 leading-7 text-easytire-muted">{{ __('commerce.pickup') }}</p>
                </header>

                @if($errors->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
                @endif

                <fieldset class="grid gap-5 border-t border-easytire-border pt-6 sm:grid-cols-2">
                    <legend class="sr-only">{{ __('commerce.checkout') }}</legend>

                    <div>
                        <label class="block text-sm font-bold" for="checkout-name">{{ __('commerce.name') }}</label>
                        <input id="checkout-name" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm @error('name') border-red-600 @enderror" name="name" type="text" autocomplete="name" required value="{{ old('name') }}" aria-describedby="checkout-name-error">
                        @error('name')<p id="checkout-name-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold" for="checkout-email">{{ __('commerce.email') }}</label>
                        <input id="checkout-email" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm @error('email') border-red-600 @enderror" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" aria-describedby="checkout-email-error">
                        @error('email')<p id="checkout-email-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold" for="checkout-phone">{{ __('commerce.phone') }}</label>
                        <input id="checkout-phone" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm @error('phone') border-red-600 @enderror" name="phone" type="tel" autocomplete="tel" required value="{{ old('phone') }}" aria-describedby="checkout-phone-error">
                        @error('phone')<p id="checkout-phone-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold" for="checkout-registration">{{ __('commerce.registration') }}</label>
                        <input id="checkout-registration" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm @error('registration') border-red-600 @enderror" name="registration" type="text" autocomplete="off" value="{{ old('registration') }}" aria-describedby="checkout-registration-error">
                        @error('registration')<p id="checkout-registration-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-bold" for="checkout-note">{{ __('commerce.note') }}</label>
                        <textarea id="checkout-note" class="easytire-input mt-2 min-h-28 w-full rounded-lg px-3 py-3 text-sm @error('note') border-red-600 @enderror" name="note" aria-describedby="checkout-note-error">{{ old('note') }}</textarea>
                        @error('note')<p id="checkout-note-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                    </div>
                </fieldset>

                <fieldset class="space-y-3 border-t border-easytire-border pt-6">
                    <legend class="sr-only">{{ __('commerce.consent') }}</legend>
                    <label class="flex min-h-11 items-center gap-3 rounded-lg border border-easytire-border bg-easytire-surface px-4 text-sm font-bold">
                        <input required type="checkbox" name="consent" value="1" @checked(old('consent')) class="size-5 rounded border-easytire-border text-easytire-brand focus:ring-easytire-brand">
                        {{ __('commerce.consent') }}
                    </label>
                    @error('consent')<p class="text-sm font-semibold text-red-700">{{ $message }}</p>@enderror

                    <label class="flex min-h-11 items-center gap-3 rounded-lg border border-easytire-border bg-easytire-surface px-4 text-sm font-bold">
                        <input required type="checkbox" name="terms" value="1" @checked(old('terms')) class="size-5 rounded border-easytire-border text-easytire-brand focus:ring-easytire-brand">
                        {{ __('commerce.terms') }}
                    </label>
                    @error('terms')<p class="text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                </fieldset>

                <button class="min-h-11 w-full rounded-lg bg-easytire-brand px-5 font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong hover:shadow-md focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand sm:w-auto" type="submit">
                    {{ __('commerce.submit') }}
                </button>
            </form>

            <aside class="rounded-2xl bg-easytire-ink p-6 text-white lg:sticky lg:top-24">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-white/70">{{ __('commerce.cart_total') }}</p>
                <div class="mt-5 space-y-3 border-y border-white/15 py-5 text-sm">
                    @forelse($cart->lines as $line)
                        <div class="flex justify-between gap-4">
                            <span>{{ $line->item->title }} × {{ $line->quantity }}</span>
                            <span class="shrink-0 font-bold">{{ number_format($line->total()->minor / 100, 2, ',', ' ') }} {{ $line->total()->currency }}</span>
                        </div>
                    @empty
                        <p class="text-white/70">{{ __('commerce.empty') }}</p>
                    @endforelse
                </div>
                <p class="mt-5 text-3xl font-black">{{ number_format($cart->total->minor / 100, 2, ',', ' ') }} {{ $cart->total->currency }}</p>
            </aside>
        </div>
    </section>
</x-layouts.storefront>
