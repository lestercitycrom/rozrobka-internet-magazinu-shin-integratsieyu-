<x-layouts.admin :title="__('admin.homepage_service_cards.title')">
    <div class="mx-auto max-w-4xl space-y-6">
        <a class="inline-flex min-h-11 items-center text-sm font-semibold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route('admin.homepage-service-collections.index', $collection->value) }}">← {{ __('admin.homepage_service_cards.collection_'.$collection->value) }}</a>
        <div><p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">{{ __('admin.homepage_service_cards.collection_'.$collection->value) }}</p><h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ $card->exists ? __('admin.homepage_service_cards.edit') : __('admin.homepage_service_cards.new') }}</h1></div>
        @if (session('status'))<p class="border-l-4 border-green-600 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif
        @if ($errors->any())<div class="border-l-4 border-red-600 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ $card->exists ? route('admin.homepage-service-collections.update', [$collection->value, $card]) : route('admin.homepage-service-collections.store', $collection->value) }}" class="space-y-6" data-dirty-form>
            @csrf
            @if ($card->exists) @method('PUT') @endif
            <section class="easytire-card grid gap-5 p-5 md:grid-cols-2">
                <label class="block text-sm font-bold" for="service-card-order">{{ __('admin.homepage_service_cards.order') }}<input id="service-card-order" required type="number" min="0" max="65535" name="sort_order" value="{{ old('sort_order', $card->sort_order ?? 0) }}" class="easytire-input mt-2 min-h-11 w-full px-3"></label>
                <label class="block text-sm font-bold" for="service-card-status">{{ __('admin.homepage_service_cards.status') }}<select id="service-card-status" name="publication_status" class="easytire-input mt-2 min-h-11 w-full px-3">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('publication_status', $card->publication_status?->value ?? 'draft') === $status->value)>{{ __('content_ui.status_'.$status->value) }}</option>@endforeach</select></label>
                <label class="block text-sm font-bold" for="service-card-service">{{ __('admin.homepage_service_cards.service') }}<select id="service-card-service" name="service_page_id" class="easytire-input mt-2 min-h-11 w-full px-3" aria-describedby="service-card-destination-help"><option value="">{{ __('admin.homepage_service_cards.custom_destination') }}</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected((string) old('service_page_id', $card->service_page_id) === (string) $service->id)>{{ $service->title_nb }}</option>@endforeach</select></label>
                <label class="block text-sm font-bold" for="service-card-url">{{ __('admin.homepage_service_cards.cta_url') }}<input id="service-card-url" name="cta_url" value="{{ old('cta_url', $card->cta_url) }}" class="easytire-input mt-2 min-h-11 w-full px-3" aria-describedby="service-card-destination-help"><span id="service-card-destination-help" class="mt-2 block text-xs font-normal text-easytire-muted">{{ __('admin.homepage_service_cards.cta_url_hint') }}</span></label>
                <label class="block text-sm font-bold md:col-span-2" for="service-card-media">{{ __('admin.homepage_service_cards.media') }}<select id="service-card-media" name="media_asset_id" class="easytire-input mt-2 min-h-11 w-full px-3"><option value="">{{ __('admin.homepage_service_cards.no_media') }}</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected((string) old('media_asset_id', $card->media_asset_id) === (string) $asset->id)>{{ $asset->original_name }}{{ $asset->alt_nb ? ' — '.$asset->alt_nb : '' }}</option>@endforeach</select><span class="mt-2 block text-xs font-normal text-easytire-muted">{{ __('admin.homepage_service_cards.media_hint') }}</span></label>
            </section>
            @foreach (['nb' => __('admin.homepage_service_cards.locale_nb'), 'ru' => __('admin.homepage_service_cards.locale_ru')] as $locale => $localeLabel)
                <section class="easytire-card space-y-5 p-5 sm:p-6"><h2 class="text-lg font-black">{{ $localeLabel }}</h2>
                    @foreach (['title' => 'text', 'description' => 'textarea', 'cta_label' => 'text'] as $name => $type)
                        @php($field = $name.'_'.$locale)
                        <label class="block text-sm font-bold" for="service-card-{{ $field }}">{{ __('admin.homepage_service_cards.'.($name === 'title' ? 'field_title' : $name)) }}
                            @if($type === 'textarea')<textarea id="service-card-{{ $field }}" name="{{ $field }}" rows="4" class="easytire-input mt-2 w-full p-3">{{ old($field, $card->{$field}) }}</textarea>
                            @else<input id="service-card-{{ $field }}" @required($name === 'title') name="{{ $field }}" value="{{ old($field, $card->{$field}) }}" class="easytire-input mt-2 min-h-11 w-full px-3">@endif
                        </label>
                    @endforeach
                </section>
            @endforeach
            <button class="min-h-11 bg-easytire-brand px-5 text-sm font-bold text-white transition hover:bg-easytire-brand-strong" type="submit">{{ __('admin.homepage_service_cards.save') }}</button>
        </form>
        @if($card->exists)<form method="POST" action="{{ route('admin.homepage-service-collections.destroy', [$collection->value, $card]) }}" onsubmit="return confirm('{{ __('admin.homepage_service_cards.delete_confirm') }}')">@csrf @method('DELETE')<button class="min-h-11 text-sm font-semibold text-red-700 underline decoration-2 underline-offset-4" type="submit">{{ __('admin.homepage_service_cards.delete') }}</button></form>@endif
    </div>
</x-layouts.admin>
