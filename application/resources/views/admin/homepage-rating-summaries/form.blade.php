<x-layouts.admin :title="__('admin.homepage_ratings.title')">
    <div class="mx-auto max-w-4xl space-y-6">
        <a class="inline-flex min-h-11 items-center text-sm font-semibold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route('admin.homepage-rating-summaries.index') }}">← {{ __('admin.homepage_ratings.title') }}</a>
        <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $summary->exists ? __('admin.homepage_ratings.edit') : __('admin.homepage_ratings.new') }}</h1>
        <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ __('admin.homepage_ratings.source_hint') }}</p>
        @if (session('status'))<p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ $summary->exists ? route('admin.homepage-rating-summaries.update', $summary) : route('admin.homepage-rating-summaries.store') }}" class="space-y-6" data-dirty-form>
            @csrf @if($summary->exists) @method('PUT') @endif
            <section class="easytire-card grid gap-5 rounded-2xl p-5 md:grid-cols-2">
                <label class="text-sm font-bold" for="rating-platform">{{ __('admin.homepage_ratings.platform') }}<select id="rating-platform" name="platform" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">@foreach($platforms as $platform)<option value="{{ $platform->value }}" @selected(old('platform', $summary->platform?->value) === $platform->value)>{{ __('admin.homepage_ratings.platform_'.$platform->value) }}</option>@endforeach</select></label>
                <label class="text-sm font-bold" for="rating-status">{{ __('admin.homepage_ratings.status') }}<select id="rating-status" name="publication_status" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('publication_status', $summary->publication_status?->value ?? 'draft') === $status->value)>{{ __('content_ui.status_'.$status->value) }}</option>@endforeach</select></label>
                <label class="text-sm font-bold" for="rating-value">{{ __('admin.homepage_ratings.rating') }}<input id="rating-value" required type="number" min="1" max="5" step="0.1" name="rating" value="{{ old('rating', $summary->rating ?? '5.0') }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3"></label>
                <label class="text-sm font-bold" for="rating-count">{{ __('admin.homepage_ratings.count_value') }}<input id="rating-count" type="number" min="0" name="review_count" value="{{ old('review_count', $summary->review_count) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3"></label>
                <label class="text-sm font-bold" for="rating-order">{{ __('admin.homepage_ratings.order') }}<input id="rating-order" required type="number" min="0" name="sort_order" value="{{ old('sort_order', $summary->sort_order ?? 0) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3"></label>
                <label class="text-sm font-bold" for="rating-source">{{ __('admin.homepage_ratings.source_url') }}<input id="rating-source" name="source_url" value="{{ old('source_url', $summary->source_url) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" inputmode="url"></label>
            </section>
            @foreach(['nb' => __('admin.homepage_ratings.locale_nb'), 'ru' => __('admin.homepage_ratings.locale_ru')] as $locale => $localeLabel)
                <section class="easytire-card space-y-5 rounded-2xl p-5"><h2 class="text-lg font-black">{{ $localeLabel }}</h2>
                    <label class="block text-sm font-bold" for="rating-count-{{ $locale }}">{{ __('admin.homepage_ratings.count_label') }}<input id="rating-count-{{ $locale }}" name="count_label_{{ $locale }}" value="{{ old('count_label_'.$locale, $summary->getAttribute('count_label_'.$locale)) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3"></label>
                    <label class="block text-sm font-bold" for="rating-link-{{ $locale }}">{{ __('admin.homepage_ratings.link_label') }}<input id="rating-link-{{ $locale }}" name="link_label_{{ $locale }}" value="{{ old('link_label_'.$locale, $summary->getAttribute('link_label_'.$locale)) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3"></label>
                </section>
            @endforeach
            <button class="et-btn et-btn--primary" type="submit">{{ __('admin.homepage_ratings.save') }}</button>
        </form>
        @if($summary->exists)<form method="POST" action="{{ route('admin.homepage-rating-summaries.destroy', $summary) }}">@csrf @method('DELETE')<button class="min-h-11 text-sm font-semibold text-red-700 underline decoration-2 underline-offset-4" type="submit">{{ __('admin.homepage_ratings.delete') }}</button></form>@endif
    </div>
</x-layouts.admin>
