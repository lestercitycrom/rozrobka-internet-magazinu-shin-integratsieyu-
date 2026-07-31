<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('admin.login.title') }} · {{ __('admin.brand') }}</title>
        <meta name="theme-color" content="#ff0000">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-easytire-surface font-sans text-easytire-ink antialiased">
        <main class="mx-auto flex min-h-screen max-w-md items-center px-5 py-10">
            <section class="w-full rounded-3xl border border-easytire-border bg-easytire-panel p-6 shadow-sm sm:p-8" aria-labelledby="admin-login-heading">
                <a href="{{ route('admin.login') }}" class="mb-10 flex w-fit items-center gap-2 font-bold tracking-tight focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" aria-label="{{ __('admin.brand') }}">
                    <img src="/brand/easytire-mark.svg" alt="" class="size-9" aria-hidden="true">
                    <span>{{ __('admin.brand') }}</span>
                </a>
                <h1 id="admin-login-heading" class="text-3xl font-semibold tracking-tight">{{ __('admin.login.heading') }}</h1>
                <p class="mt-2 text-sm leading-6 text-easytire-muted">{{ __('admin.login.intro') }}</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-r-lg border-l-4 border-easytire-brand bg-easytire-brand-soft px-4 py-3 text-sm" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium">{{ __('admin.login.email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="mt-2 block w-full rounded-lg border border-easytire-border bg-white px-3 py-2.5 text-base focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand" aria-describedby="email-error">
                        @error('email') <p id="email-error" class="mt-2 text-sm text-easytire-muted">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium">{{ __('admin.login.password') }}</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-lg border border-easytire-border bg-white px-3 py-2.5 text-base focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-easytire-brand px-4 py-3 text-sm font-semibold text-white hover:bg-easytire-brand-strong focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand">{{ __('admin.login.submit') }}</button>
                </form>
            </section>
        </main>
    </body>
</html>
