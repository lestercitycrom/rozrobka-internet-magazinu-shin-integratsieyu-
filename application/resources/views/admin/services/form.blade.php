<x-layouts.admin :title="__('admin.services.title')">
    <div class="max-w-4xl space-y-6">
        <a class="text-sm underline underline-offset-4 hover:text-easytire-brand focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand" href="{{ route('admin.services.index') }}">← {{ __('admin.services.title') }}</a>
        <h1 class="text-3xl font-semibold">{{ $page->exists ? __('admin.services.edit') : __('admin.services.new') }}</h1>

        @if (session('status'))
            <p class="border-l-4 border-easytire-brand bg-easytire-brand-soft p-3 text-sm">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="border-l-4 border-easytire-brand bg-easytire-brand-soft p-4" role="alert">
                <p class="font-semibold">{{ __('services.validation') }}</p>
                <ul class="mt-2 list-inside list-disc text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $page->exists ? route('admin.services.update', $page) : route('admin.services.store') }}" class="space-y-6">
            @csrf
            @if ($page->exists) @method('PUT') @endif
            <section class="grid gap-4 border border-easytire-border bg-easytire-panel p-5 md:grid-cols-4">
                @foreach (['slug' => 'text', 'sort_order' => 'number'] as $field => $type)
                    <label class="text-sm font-medium">{{ __('admin.services.'.($field === 'sort_order' ? 'order' : $field)) }}<input name="{{ $field }}" type="{{ $type }}" @if($field === 'sort_order') min="0" @endif value="{{ old($field, $page->$field ?? ($field === 'sort_order' ? 0 : '')) }}" class="mt-1 w-full rounded-md border border-easytire-border bg-easytire-panel px-3 py-2 text-easytire-ink shadow-sm focus:border-easytire-brand focus:ring-2 focus:ring-easytire-brand/20" required></label>
                @endforeach
                <label class="text-sm font-medium">{{ __('admin.services.status') }}<select name="publication_status" class="mt-1 w-full rounded-md border border-easytire-border bg-easytire-panel px-3 py-2 text-easytire-ink shadow-sm focus:border-easytire-brand focus:ring-2 focus:ring-easytire-brand/20">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('publication_status', $page->publication_status?->value ?? 'draft') === $status->value)>{{ __('services.statuses.'.$status->value) }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">{{ __('service_flows.label') }}<select name="request_flow" class="mt-1 w-full rounded-md border border-easytire-border bg-easytire-panel px-3 py-2 text-easytire-ink shadow-sm focus:border-easytire-brand focus:ring-2 focus:ring-easytire-brand/20">@foreach ($requestFlows as $requestFlow)<option value="{{ $requestFlow->value }}" @selected(old('request_flow', $page->request_flow?->value ?? \App\Modules\Content\Enums\ServiceRequestFlow::Generic->value) === $requestFlow->value)>{{ __('service_flows.'.$requestFlow->value) }}</option>@endforeach</select><span class="mt-1 block text-xs text-easytire-muted">{{ __('service_flows.help') }}</span></label>
                <label class="text-sm font-medium md:col-span-4">{{ __('admin.services.canonical_path') }}<input name="canonical_path" value="{{ old('canonical_path', $page->canonical_path) }}" placeholder="dekkskift-tromso" class="mt-1 w-full rounded-md border border-easytire-border bg-easytire-panel px-3 py-2 text-easytire-ink shadow-sm focus:border-easytire-brand focus:ring-2 focus:ring-easytire-brand/20"><span class="mt-1 block text-xs text-easytire-muted">{{ __('admin.services.canonical_path_hint') }}</span></label>
            </section>

            @foreach (['nb' => 'NO', 'ru' => 'RU'] as $locale => $label)
                <section class="space-y-4 border border-easytire-border bg-easytire-panel p-5">
                    <h2 class="font-semibold">{{ $label }}</h2>
                    @if ($locale === 'ru') <p class="text-sm text-easytire-muted">{{ __('admin.services.ru_hint') }}</p> @endif
                    <p class="text-sm text-easytire-muted">{{ __('admin.services.markdown_hint') }}</p>
                    @foreach (['title', 'excerpt', 'body', 'seo_title', 'seo_description'] as $name)
                        @php($field = $name.'_'.$locale)
                        <label class="block text-sm font-medium">{{ __('admin.services.'.$name) }}
                            @if (in_array($name, ['excerpt', 'body', 'seo_description'], true))
                                <textarea name="{{ $field }}" class="mt-1 w-full resize-y rounded-md border border-easytire-border bg-easytire-panel px-3 py-2 text-easytire-ink shadow-sm focus:border-easytire-brand focus:ring-2 focus:ring-easytire-brand/20" rows="{{ $name === 'body' ? 14 : 3 }}">{{ old($field, $page->$field) }}</textarea>
                            @else
                                <input name="{{ $field }}" value="{{ old($field, $page->$field) }}" class="mt-1 w-full rounded-md border border-easytire-border bg-easytire-panel px-3 py-2 text-easytire-ink shadow-sm focus:border-easytire-brand focus:ring-2 focus:ring-easytire-brand/20" @required($name === 'title')>
                            @endif
                        </label>
                    @endforeach
                </section>
            @endforeach
            <button class="bg-easytire-brand px-5 py-3 text-sm font-semibold text-white hover:bg-easytire-brand-strong focus:outline-2 focus:outline-offset-2 focus:outline-easytire-brand">{{ __('admin.services.save') }}</button>
        </form>
    </div>
</x-layouts.admin>
