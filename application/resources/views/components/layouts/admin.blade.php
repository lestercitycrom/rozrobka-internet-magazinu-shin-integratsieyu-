<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? __('admin.brand') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-[#F5F5F2] font-sans text-[#111111] antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">
            {{ __('admin.topbar.menu') }}
        </a>
        <div class="min-h-screen md:grid md:grid-cols-[15rem_1fr]">
            <aside class="hidden border-r border-[#D8D8D2] bg-white md:flex md:flex-col" aria-label="{{ __('admin.topbar.menu') }}">
                @include('admin.partials.navigation')
            </aside>
            <div class="min-w-0">
                <header class="flex min-h-16 items-center justify-between border-b border-[#D8D8D2] bg-white px-4 md:px-8">
                    <details class="md:hidden">
                        <summary class="cursor-pointer list-none border border-[#D8D8D2] px-3 py-2 text-sm font-medium focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">{{ __('admin.topbar.menu') }}</summary>
                        <div class="absolute left-0 top-16 z-20 w-64 border-b border-r border-[#D8D8D2] bg-white">
                            @include('admin.partials.navigation')
                        </div>
                    </details>
                    <p class="text-sm text-[#666660]">{{ $title ?? __('admin.dashboard.title') }}</p>
                    @include('admin.partials.topbar')
                </header>
                <main id="main-content" class="mx-auto w-full max-w-6xl px-4 py-8 md:px-8 md:py-12">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
