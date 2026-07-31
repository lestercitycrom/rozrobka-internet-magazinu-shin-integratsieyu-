<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('admin.login.title') }} · {{ __('admin.brand') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-[#F5F5F2] font-sans text-[#111111] antialiased">
        <main class="mx-auto flex min-h-screen max-w-md items-center px-5 py-10">
            <section class="w-full border border-[#D8D8D2] bg-white p-6 sm:p-8" aria-labelledby="admin-login-heading">
                <p class="mb-10 text-sm font-semibold tracking-tight">{{ __('admin.brand') }}</p>
                <h1 id="admin-login-heading" class="text-3xl font-semibold tracking-tight">{{ __('admin.login.heading') }}</h1>
                <p class="mt-2 text-sm leading-6 text-[#666660]">{{ __('admin.login.intro') }}</p>

                @if ($errors->any())
                    <div class="mt-6 border-l-2 border-[#111111] bg-[#F5F5F2] px-4 py-3 text-sm" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium">{{ __('admin.login.email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="mt-2 block w-full border border-[#D8D8D2] bg-white px-3 py-2.5 text-base focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]" aria-describedby="email-error">
                        @error('email') <p id="email-error" class="mt-2 text-sm text-[#666660]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium">{{ __('admin.login.password') }}</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="mt-2 block w-full border border-[#D8D8D2] bg-white px-3 py-2.5 text-base focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">
                    </div>
                    <button type="submit" class="w-full bg-[#111111] px-4 py-3 text-sm font-semibold text-white hover:bg-[#333333] focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">{{ __('admin.login.submit') }}</button>
                </form>
            </section>
        </main>
    </body>
</html>
