<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101010">
    <title>{{ __('admin.login.title') }} · {{ __('admin.brand') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/brand/easytire-icon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
    <main class="et-login">
        <div class="et-login__visual">
            <img src="/brand/workshop.webp" width="1400" height="900" alt="{{ __('pro.content.workshop_image_alt') }}">
            <div class="et-login__caption">
                <p class="et-eyebrow et-eyebrow--light">{{ __('pro.admin.login_visual_kicker') }}</p>
                <h2 class="et-h1">{{ __('pro.admin.login_visual_title') }}</h2>
                <p class="et-lead et-lead--light">{{ __('pro.admin.login_visual_text') }}</p>
            </div>
        </div>

        <div class="et-login__panel">
            <section class="et-login__card" aria-labelledby="admin-login-heading">
                <img class="et-login__logo" src="/brand/easytire-logo.png" width="900" height="243" alt="EasyTire">
                <p class="et-eyebrow">{{ __('pro.admin.login_kicker') }}</p>
                <h1 class="et-h2" id="admin-login-heading">{{ __('admin.login.heading') }}</h1>
                <p class="et-copy">{{ __('admin.login.intro') }}</p>

                @if ($errors->any())
                    <div class="et-alert et-alert--error" style="margin-top:1.2rem" role="alert"><span class="et-alert__icon" aria-hidden="true">!</span><div>{{ $errors->first() }}</div></div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}" class="et-stack" style="margin-top:1.5rem">
                    @csrf
                    <label>
                        <span class="et-label">{{ __('admin.login.email') }}</span>
                        <input class="et-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')<span class="et-form-error" id="email-error">{{ $message }}</span>@enderror
                    </label>
                    <label>
                        <span class="et-label">{{ __('admin.login.password') }}</span>
                        <input class="et-input" id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')<span class="et-form-error" id="password-error">{{ $message }}</span>@enderror
                    </label>
                    <button class="et-btn et-btn--primary et-btn--full" type="submit">{{ __('admin.login.submit') }}</button>
                    <a class="et-btn et-btn--link" href="{{ app()->getLocale() === 'ru' ? route('storefront.preview.home') : route('storefront.home') }}">{{ __('pro.admin.back_storefront') }}</a>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
