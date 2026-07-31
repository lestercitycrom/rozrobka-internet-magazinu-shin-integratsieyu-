@php($routePrefix = app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.')

<x-layouts.storefront :title="__('fitment.title')">
    <section class="border-b border-easytire-border bg-easytire-panel">
        <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16">
            <div class="max-w-3xl">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                <h1 class="mt-3 text-4xl font-black tracking-tight text-easytire-ink sm:text-5xl">{{ __('fitment.title') }}</h1>
                <p class="mt-5 max-w-2xl text-base leading-7 text-easytire-muted sm:text-lg">{{ __('fitment.notice') }}</p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14">
        <div class="grid max-w-5xl gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)] lg:items-start">
            <div class="rounded-2xl border border-easytire-border bg-easytire-panel p-6 shadow-sm sm:p-8">
                <h2 class="text-2xl font-black tracking-tight text-easytire-ink">{{ __('fitment.registration') }}</h2>
                <p class="mt-2 text-sm leading-6 text-easytire-muted">{{ __('fitment.notice') }}</p>

                <form method="POST" action="{{ url()->current() }}" class="mt-7 space-y-5">
                    @csrf
                    <div>
                        <label for="registration" class="block text-sm font-bold text-easytire-ink">{{ __('fitment.registration') }}</label>
                        <input
                            id="registration"
                            name="registration"
                            type="text"
                            value="{{ old('registration') }}"
                            required
                            maxlength="16"
                            autocomplete="off"
                            autocapitalize="characters"
                            aria-describedby="registration-help{{ $errors->has('registration') ? ' registration-error' : '' }}"
                            @class(['mt-2 block min-h-12 w-full rounded-lg border bg-white px-4 text-base font-semibold uppercase tracking-wide text-easytire-ink outline-none transition placeholder:text-slate-400 focus:border-easytire-brand focus:ring-4 focus:ring-easytire-brand/15', 'border-red-600' => $errors->has('registration'), 'border-easytire-border' => ! $errors->has('registration')])
                        >
                        <p id="registration-help" class="mt-2 text-sm leading-6 text-easytire-muted">{{ __('fitment.notice') }}</p>
                        @error('registration')
                            <p id="registration-error" class="mt-2 text-sm font-semibold text-red-700" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-easytire-brand px-6 py-3 text-base font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand sm:w-auto">
                        {{ __('fitment.submit') }}
                    </button>
                </form>
            </div>

            @if (isset($fitment) && $fitment)
                <aside class="rounded-2xl border border-easytire-border bg-white p-6 shadow-sm sm:p-8" aria-labelledby="fitment-result-title">
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                    <h2 id="fitment-result-title" class="mt-3 text-2xl font-black tracking-tight text-easytire-ink">{{ $fitment->make }} {{ $fitment->model }}</h2>
                    <p class="mt-3 text-sm leading-6 text-easytire-muted">{{ __('fitment.results_notice') }}</p>
                    <ul class="mt-6 space-y-3" aria-label="{{ __('fitment.results_notice') }}">
                        @foreach ($fitment->tyres as $tyre)
                            <li>
                                <a href="{{ route($routePrefix.'tires.index', ['width' => $tyre['width'], 'aspect' => $tyre['aspect'], 'rim' => $tyre['rim']]) }}" class="flex min-h-12 items-center justify-between rounded-lg border border-easytire-border px-4 font-bold text-easytire-ink transition hover:border-easytire-brand hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand">
                                    <span>{{ $tyre['width'] }}/{{ $tyre['aspect'] }} R{{ $tyre['rim'] }}</span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </aside>
            @else
                <aside class="rounded-2xl border border-easytire-border bg-white p-6 shadow-sm sm:p-8" aria-labelledby="fitment-fallback-title">
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                    <h2 id="fitment-fallback-title" class="mt-3 text-2xl font-black tracking-tight text-easytire-ink">{{ __('fitment.fallback') }}</h2>
                    <p class="mt-3 text-sm leading-6 text-easytire-muted">{{ __('fitment.notice') }}</p>
                    <a href="{{ route($routePrefix.'tires.index') }}" class="mt-6 inline-flex min-h-12 items-center justify-center rounded-full border border-easytire-ink px-5 py-3 text-sm font-bold text-easytire-ink transition hover:border-easytire-brand hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand">
                        {{ __('fitment.manual') }}
                    </a>
                </aside>
            @endif
        </div>
    </section>
</x-layouts.storefront>
