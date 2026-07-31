<div class="flex items-center gap-3">
    <span class="hidden text-sm text-[#666660] sm:inline">{{ auth()->user()->name }}</span>
    <div class="flex border border-[#D8D8D2]" role="group" aria-label="{{ __('admin.topbar.locale') }}">
        @foreach (['nb' => 'NO', 'ru' => 'RU'] as $locale => $label)
            <form method="POST" action="{{ route('admin.locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                <button type="submit" data-testid="switch-language-{{ $locale }}" aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}" class="px-2 py-1 text-xs font-semibold {{ app()->getLocale() === $locale ? 'bg-[#111111] text-white' : 'bg-white text-[#111111] hover:bg-[#ECECE7]' }} focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">{{ $label }}</button>
            </form>
        @endforeach
    </div>
    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="text-sm underline underline-offset-4 focus:outline-2 focus:outline-offset-2 focus:outline-[#111111]">{{ __('admin.topbar.logout') }}</button>
    </form>
</div>
