<x-layouts.admin :title="__('storefront_settings.title')">
    @php
        $value = static fn (string $field): string => (string) old($field, $settings->{$field} ?? ($defaults[$field] ?? ''));
        $localeFields = static function (string $locale): array {
            return [
                "hero_kicker_{$locale}", "hero_title_{$locale}", "hero_text_{$locale}",
                "hero_primary_label_{$locale}",
            ];
        };
        $editorialSections = [
            'process' => ['process_kicker', 'process_title', 'process_lead', 'process_1_title', 'process_1_text', 'process_2_title', 'process_2_text', 'process_3_title', 'process_3_text', 'process_4_title', 'process_4_text', 'process_cta'],
            'logistics' => ['logistics_kicker', 'logistics_title', 'hotel_kicker', 'hotel_title', 'hotel_text', 'hotel_cta', 'mobile_kicker', 'mobile_title', 'mobile_text', 'mobile_cta', 'mobile_details_title', 'mobile_details_body_1', 'mobile_details_body_2'],
            'contact_copy' => ['contact_kicker', 'contact_title', 'contact_text', 'contact_map_note', 'business_kicker', 'business_title', 'business_text', 'business_cta', 'business_partners_heading'],
        ];
        $editorialLabel = static function (string $field): string {
            if (preg_match('/^process_([1-4])_(title|text)$/', $field, $matches) === 1) {
                return __('storefront_settings.process_step_'.$matches[2], ['number' => $matches[1]]);
            }

            return __('storefront_settings.'.$field);
        };
        $isLongEditorial = static fn (string $field): bool => str_ends_with($field, '_text') || str_contains($field, '_body_') || in_array($field, ['process_lead', 'story_quote'], true);
    @endphp

    <div class="mx-auto max-w-5xl space-y-8">
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('storefront_settings.heading') }}</h1>
            <p class="mt-3 max-w-3xl leading-7 text-easytire-muted">{{ __('storefront_settings.intro') }}</p>
        </header>

        @if (session('status'))
            <p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('admin.storefront-settings.update') }}" class="space-y-7" data-dirty-form>
            @csrf
            @method('PUT')

            <section class="easytire-card space-y-6 rounded-2xl p-5 sm:p-8" aria-labelledby="storefront-hero-heading">
                <div><h2 id="storefront-hero-heading" class="text-xl font-black">{{ __('storefront_settings.hero') }}</h2><p class="mt-1 text-sm text-easytire-muted">{{ __('storefront_settings.hero_hint') }}</p></div>
                <div class="grid gap-5 lg:grid-cols-2">
                    @foreach (['nb' => __('storefront_settings.locale_nb'), 'ru' => __('storefront_settings.locale_ru')] as $locale => $label)
                        <fieldset class="space-y-4 rounded-xl border border-easytire-border p-4">
                            <legend class="px-1 text-sm font-black">{{ $label }}</legend>
                            @foreach ($localeFields($locale) as $field)
                                @php($isLong = str_ends_with($field, '_text_'.$locale))
                                <label class="block text-sm font-bold" for="{{ $field }}">{{ __('storefront_settings.'.$field) }}
                                    @if ($isLong)
                                        <textarea id="{{ $field }}" name="{{ $field }}" rows="3" class="easytire-input mt-2 w-full rounded-lg px-3 py-2">{{ $value($field) }}</textarea>
                                    @else
                                        <input id="{{ $field }}" name="{{ $field }}" value="{{ $value($field) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                                    @endif
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach (['hero_primary_url'] as $field)
                        <label class="block text-sm font-bold" for="{{ $field }}">{{ __('storefront_settings.'.$field) }}
                            <input id="{{ $field }}" name="{{ $field }}" value="{{ $value($field) }}" placeholder="{{ __('storefront_settings.internal_url_hint') }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 @error($field) border-red-600 @enderror">
                        </label>
                    @endforeach
                </div>
                <label class="block text-sm font-bold" for="hero_media_asset_id">{{ __('storefront_settings.hero_media') }}
                    <select id="hero_media_asset_id" name="hero_media_asset_id" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                        <option value="">{{ __('storefront_settings.default_hero_media') }}</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}" @selected((string) old('hero_media_asset_id', $settings->hero_media_asset_id) === (string) $asset->id)>{{ $asset->original_name }}{{ $asset->alt_nb ? ' — '.$asset->alt_nb : '' }}</option>
                        @endforeach
                    </select>
                    <span class="mt-2 block text-xs font-normal text-easytire-muted">{{ __('storefront_settings.hero_media_hint') }}</span>
                </label>
            </section>

            <section class="easytire-card space-y-6 rounded-2xl p-5 sm:p-8" aria-labelledby="storefront-contact-heading">
                <div><h2 id="storefront-contact-heading" class="text-xl font-black">{{ __('storefront_settings.contact') }}</h2><p class="mt-1 text-sm text-easytire-muted">{{ __('storefront_settings.contact_hint') }}</p></div>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach (['phone_display', 'phone_href', 'email', 'business_address', 'map_pin_address', 'map_url', 'map_embed_url', 'instagram_url', 'snapchat_url', 'facebook_url'] as $field)
                        <label class="block text-sm font-bold" for="{{ $field }}">{{ __('storefront_settings.'.$field) }}
                            <input id="{{ $field }}" name="{{ $field }}" value="{{ $value($field) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 @error($field) border-red-600 @enderror">
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="easytire-card space-y-6 rounded-2xl p-5 sm:p-8" aria-labelledby="storefront-store-heading">
                <div><h2 id="storefront-store-heading" class="text-xl font-black">{{ __('storefront_settings.store') }}</h2><p class="mt-1 text-sm text-easytire-muted">{{ __('storefront_settings.store_hint') }}</p></div>
                <div class="grid gap-5 lg:grid-cols-2">
                    @foreach (['nb' => __('storefront_settings.locale_nb'), 'ru' => __('storefront_settings.locale_ru')] as $locale => $label)
                        <fieldset class="space-y-4 rounded-xl border border-easytire-border p-4">
                            <legend class="px-1 text-sm font-black">{{ $label }}</legend>
                            @foreach (["store_heading_{$locale}", "store_lead_{$locale}"] as $field)
                                <label class="block text-sm font-bold" for="{{ $field }}">{{ __('storefront_settings.'.str_replace('_'.$locale, '', $field)) }}
                                    @if (str_contains($field, '_lead_'))
                                        <textarea id="{{ $field }}" name="{{ $field }}" rows="3" class="easytire-input mt-2 w-full rounded-lg px-3 py-2">{{ $value($field) }}</textarea>
                                    @else
                                        <input id="{{ $field }}" name="{{ $field }}" value="{{ $value($field) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                                    @endif
                                </label>
                            @endforeach
                            @foreach ([1, 2, 3] as $step)
                                <div class="rounded-lg bg-easytire-surface p-3">
                                    <p class="text-xs font-black uppercase tracking-wide text-easytire-muted">{{ __('storefront_settings.benefit', ['number' => $step]) }}</p>
                                    @foreach (["store_benefit_{$step}_title_{$locale}", "store_benefit_{$step}_text_{$locale}"] as $field)
                                        <label class="mt-3 block text-sm font-bold" for="{{ $field }}">{{ str_contains($field, '_title_') ? __('storefront_settings.benefit_title') : __('storefront_settings.benefit_text') }}
                                            @if (str_contains($field, '_text_'))
                                                <textarea id="{{ $field }}" name="{{ $field }}" rows="2" class="easytire-input mt-2 w-full rounded-lg px-3 py-2">{{ $value($field) }}</textarea>
                                            @else
                                                <input id="{{ $field }}" name="{{ $field }}" value="{{ $value($field) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
            </section>

            <section class="easytire-card space-y-6 rounded-2xl p-5 sm:p-8" aria-labelledby="storefront-editorial-heading">
                <div><h2 id="storefront-editorial-heading" class="text-xl font-black">{{ __('storefront_settings.home_editorial') }}</h2><p class="mt-1 text-sm text-easytire-muted">{{ __('storefront_settings.home_editorial_hint') }}</p></div>
                @foreach ($editorialSections as $section => $fields)
                    <div class="rounded-xl border border-easytire-border p-4 sm:p-5">
                        <h3 class="text-base font-black">{{ __('storefront_settings.'.$section) }}</h3>
                        <div class="mt-4 grid gap-5 lg:grid-cols-2">
                            @foreach (['nb' => __('storefront_settings.locale_nb'), 'ru' => __('storefront_settings.locale_ru')] as $locale => $localeLabel)
                                <fieldset class="space-y-3 rounded-lg bg-easytire-surface p-4">
                                    <legend class="px-1 text-sm font-black">{{ $localeLabel }}</legend>
                                    @foreach ($fields as $baseField)
                                        @php($field = $baseField.'_'.$locale)
                                        <label class="block text-sm font-bold" for="{{ $field }}">{{ $editorialLabel($baseField) }}
                                            @if ($isLongEditorial($baseField))
                                                <textarea id="{{ $field }}" name="{{ $field }}" rows="2" class="easytire-input mt-2 w-full rounded-lg px-3 py-2">{{ $value($field) }}</textarea>
                                            @else
                                                <input id="{{ $field }}" name="{{ $field }}" value="{{ $value($field) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                                            @endif
                                        </label>
                                    @endforeach
                                </fieldset>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="rounded-xl border border-easytire-border p-4 sm:p-5">
                    <h3 class="text-base font-black">{{ __('storefront_settings.section_media') }}</h3>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        @foreach (['home_store_media_asset_id', 'hotel_media_asset_id', 'mobile_media_asset_id'] as $field)
                            <label class="block text-sm font-bold" for="{{ $field }}">{{ __('storefront_settings.'.str_replace('_asset_id', '', $field)) }}
                                <select id="{{ $field }}" name="{{ $field }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                                    <option value="">{{ __('storefront_settings.default_section_media') }}</option>
                                    @foreach ($assets as $asset)
                                        <option value="{{ $asset->id }}" @selected((string) old($field, $settings->{$field}) === (string) $asset->id)>{{ $asset->original_name }}{{ $asset->alt_nb ? ' — '.$asset->alt_nb : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                        <label class="block text-sm font-bold" for="hotel_video_media_asset_id">{{ __('storefront_settings.hotel_video_media') }}
                            <select id="hotel_video_media_asset_id" name="hotel_video_media_asset_id" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">
                                <option value="">{{ __('storefront_settings.default_hotel_video_media') }}</option>
                                @foreach ($videoAssets as $asset)
                                    <option value="{{ $asset->id }}" @selected((string) old('hotel_video_media_asset_id', $settings->hotel_video_media_asset_id) === (string) $asset->id)>{{ $asset->original_name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>
            </section>

            <button class="min-h-11 rounded-lg bg-easytire-brand px-5 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong" type="submit">{{ __('storefront_settings.save') }}</button>
        </form>
    </div>
</x-layouts.admin>
