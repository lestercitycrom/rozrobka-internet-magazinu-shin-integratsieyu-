<div class="flex items-center gap-3">
    <span class="hidden text-sm text-easytire-muted sm:inline">{{ auth()->user()->name }}</span>
    <div class="flex border border-easytire-border" role="group" aria-label="{{ __('admin.topbar.locale') }}">
        @foreach (['nb' => 'NO', 'ru' => 'RU'] as $locale => $label)
            <form method="POST" action="{{ route('admin.locale.update') }}" data-admin-locale-form>
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                <button type="submit" data-testid="switch-language-{{ $locale }}" aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}" class="px-2 py-1 text-xs font-semibold {{ app()->getLocale() === $locale ? 'bg-easytire-brand text-white' : 'bg-easytire-panel text-easytire-ink hover:bg-easytire-brand-soft' }} focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand">{{ $label }}</button>
            </form>
        @endforeach
    </div>
    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="text-sm underline underline-offset-4 focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">{{ __('admin.topbar.logout') }}</button>
    </form>
</div>
