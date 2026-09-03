@php
    $adminName = auth()->user()->name ?: auth()->user()->email;
    $initials = collect(preg_split('/\s+/u', trim($adminName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<div class="et-admin-user">
    <div class="et-admin-locale" role="group" aria-label="{{ __('admin.topbar.locale') }}">
        @foreach (['nb' => 'NB', 'ru' => 'RU'] as $locale => $label)
            <form method="POST" action="{{ route('admin.locale.update') }}" data-admin-locale-form>
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                <button type="submit" data-testid="switch-language-{{ $locale }}" aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}" @class(['et-admin-locale__button', 'is-active' => app()->getLocale() === $locale])>{{ $label }}</button>
            </form>
        @endforeach
    </div>

    <div class="et-admin-avatar" aria-hidden="true">{{ $initials ?: 'ET' }}</div>
    <div class="et-admin-user__identity">
        <strong>{{ $adminName }}</strong>
        <small>{{ __('pro.admin.role') }}</small>
    </div>

    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button class="et-btn et-btn--outline et-btn--small" type="submit" data-testid="admin-logout">{{ __('admin.topbar.logout') }}</button>
    </form>
</div>
