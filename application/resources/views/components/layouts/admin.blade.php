<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? __('admin.brand') }}</title>
        <meta name="theme-color" content="#ff0000">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="admin-shell min-h-screen font-sans text-easytire-ink antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">
            {{ __('admin.topbar.menu') }}
        </a>
        <div class="min-h-screen md:grid md:grid-cols-[16.5rem_1fr]">
            <aside class="hidden border-r border-easytire-border bg-easytire-panel/95 md:flex md:flex-col" aria-label="{{ __('admin.topbar.menu') }}">
                @include('admin.partials.navigation')
            </aside>
            <div class="min-w-0">
                <header class="sticky top-0 z-20 flex min-h-[4.5rem] items-center justify-between border-b border-easytire-border bg-easytire-panel/95 px-4 backdrop-blur md:px-8">
                    <details class="md:hidden">
                        <summary class="min-h-11 cursor-pointer list-none rounded-lg border border-easytire-border px-3 py-2 text-sm font-bold focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand">{{ __('admin.topbar.menu') }}</summary>
                        <div class="absolute left-0 top-[4.5rem] z-20 w-72 border-b border-r border-easytire-border bg-easytire-panel shadow-xl">
                            @include('admin.partials.navigation')
                        </div>
                    </details>
                    <p class="text-sm text-easytire-muted">{{ $title ?? __('admin.dashboard.title') }}</p>
                    @include('admin.partials.topbar')
                </header>
                <main id="main-content" data-admin-dirty-root data-admin-unsaved-message="{{ __('admin.topbar.unsaved_changes') }}" class="mx-auto w-full max-w-7xl px-4 py-8 md:px-8 md:py-12">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
