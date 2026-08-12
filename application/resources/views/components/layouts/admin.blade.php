<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101010">
    <title>{{ $title ?? __('admin.brand') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon-96x96.png" sizes="96x96" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="et-admin-body">
    <a class="skip-link" href="#main-content">{{ __('storefront.skip') }}</a>

    <div class="et-admin-shell">
        <aside class="et-admin-sidebar" aria-label="{{ __('admin.topbar.menu') }}" data-admin-sidebar data-testid="admin-sidebar">
            @include('admin.partials.navigation')
        </aside>

        <div class="et-admin-main">
            <header class="et-admin-topbar">
                <div class="et-admin-topbar__title">
                    <button class="et-admin-menu" type="button" aria-label="{{ __('admin.topbar.menu') }}" aria-expanded="false" data-admin-menu data-testid="admin-menu-toggle">☰</button>
                    <div>
                        <strong>{{ $title ?? __('admin.dashboard.title') }}</strong>
                        <span class="et-caption">{{ __('admin.brand') }}</span>
                    </div>
                </div>
                @include('admin.partials.topbar')
            </header>

            <main id="main-content" class="et-admin-content et-admin-main-content" data-admin-dirty-root data-admin-unsaved-message="{{ __('admin.topbar.unsaved_changes') }}">
                {{ $slot }}
            </main>
        </div>
    </div>

    <div class="et-admin-overlay" data-admin-overlay data-testid="admin-overlay"></div>
    <div class="et-toast" role="status" aria-live="polite" data-toast></div>
</body>
</html>
