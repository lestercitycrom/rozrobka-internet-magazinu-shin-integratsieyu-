<x-layouts.storefront :title="__('errors.title', [], 'nb')">
    <section class="mx-auto max-w-3xl px-5 py-20 sm:px-8">
        <p class="text-sm font-bold text-easytire-brand">EasyTire</p>
        <h1 class="mt-3 text-4xl font-black tracking-tight">{{ __('errors.heading', [], 'nb') }}</h1>
        <p class="mt-4 text-easytire-muted">{{ __('errors.message', [], 'nb') }}</p>
        <a class="mt-8 inline-flex rounded-full bg-easytire-brand px-5 py-3 font-bold text-white hover:bg-easytire-brand-strong focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" href="{{ app()->getLocale() === 'ru' ? '/ru' : '/' }}">{{ __('errors.home') }}</a>
    </section>
</x-layouts.storefront>
