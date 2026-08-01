<x-layouts.admin :title="__('admin.content.title')">
    <div class="mx-auto max-w-4xl space-y-6">
        <a class="inline-flex min-h-11 items-center text-sm font-semibold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route('admin.content-pages.index') }}">
            ← {{ __('admin.content.title') }}
        </a>

        <h1 class="text-3xl font-black tracking-tight sm:text-4xl">
            {{ $page->exists ? __('admin.content.edit') : __('admin.content.new') }}
        </h1>

        @if (session('status'))
            <p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $page->exists ? route('admin.content-pages.update', $page) : route('admin.content-pages.store') }}" class="space-y-6">
            @csrf
            @if ($page->exists)
                @method('PUT')
            @endif

            <section class="easytire-card grid gap-5 rounded-2xl p-5 md:grid-cols-3">
                <label class="block text-sm font-bold" for="content-slug">
                    {{ __('admin.content.slug') }}
                    <input id="content-slug" required name="slug" value="{{ old('slug', $page->slug) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                </label>
                <label class="block text-sm font-bold" for="content-order">
                    {{ __('admin.content.order') }}
                    <input id="content-order" required type="number" min="0" name="sort_order" value="{{ old('sort_order', $page->sort_order ?? 0) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                </label>
                <label class="block text-sm font-bold" for="content-status">
                    {{ __('admin.content.status') }}
                    <select id="content-status" name="publication_status" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('publication_status', $page->publication_status?->value ?? 'draft') === $status->value)>
                                {{ __('content_ui.status_'.$status->value) }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </section>

            @foreach (['nb' => __('admin.content.locale_nb'), 'ru' => __('admin.content.locale_ru')] as $locale => $localeLabel)
                <section class="easytire-card space-y-5 rounded-2xl p-5 sm:p-6">
                    <h2 class="text-lg font-black">{{ $localeLabel }}</h2>
                    @foreach (['title' => 'text', 'excerpt' => 'textarea', 'body' => 'textarea', 'meta_title' => 'text', 'meta_description' => 'textarea'] as $name => $type)
                        @php($field = $name.'_'.$locale)
                        <label class="block text-sm font-bold" for="content-{{ $field }}">
                            {{ $name === 'title' ? __('admin.content.field_title') : __('admin.content.'.$name) }}
                            @if ($type === 'textarea')
                                <textarea id="content-{{ $field }}" name="{{ $field }}" rows="3" class="easytire-input mt-2 w-full rounded-lg p-3">{{ old($field, $page->{$field}) }}</textarea>
                            @else
                                <input id="content-{{ $field }}" @required($name === 'title') name="{{ $field }}" value="{{ old($field, $page->{$field}) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                            @endif
                        </label>
                    @endforeach
                </section>
            @endforeach

            <button class="min-h-11 rounded-lg bg-easytire-brand px-5 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong" type="submit">
                {{ __('admin.content.save') }}
            </button>
        </form>

        @if ($page->exists)
            <form method="POST" action="{{ route('admin.content-pages.destroy', $page) }}">
                @csrf
                @method('DELETE')
                <button class="min-h-11 text-sm font-semibold text-red-700 underline decoration-2 underline-offset-4" type="submit">{{ __('admin.content.delete') }}</button>
            </form>
        @endif
    </div>
</x-layouts.admin>
