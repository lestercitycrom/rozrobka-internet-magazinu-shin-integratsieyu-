<x-layouts.admin :title="__('admin.homepage_reviews.title')">
    <div class="mx-auto max-w-4xl space-y-6">
        <a class="inline-flex min-h-11 items-center text-sm font-semibold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route('admin.homepage-reviews.index') }}">← {{ __('admin.homepage_reviews.title') }}</a>
        <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $review->exists ? __('admin.homepage_reviews.edit') : __('admin.homepage_reviews.new') }}</h1>
        <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ __('admin.homepage_reviews.source_hint') }}</p>
        @if (session('status'))<p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ $review->exists ? route('admin.homepage-reviews.update', $review) : route('admin.homepage-reviews.store') }}" class="space-y-6" data-dirty-form>
            @csrf
            @if ($review->exists) @method('PUT') @endif
            <section class="easytire-card grid gap-5 rounded-2xl p-5 md:grid-cols-3">
                <label class="block text-sm font-bold" for="review-order">{{ __('admin.homepage_reviews.order') }}<input id="review-order" required type="number" min="0" name="sort_order" value="{{ old('sort_order', $review->sort_order ?? 0) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3"></label>
                <label class="block text-sm font-bold" for="review-status">{{ __('admin.homepage_reviews.status') }}<select id="review-status" name="publication_status" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('publication_status', $review->publication_status?->value ?? 'draft') === $status->value)>{{ __('content_ui.status_'.$status->value) }}</option>@endforeach</select></label>
                <label class="block text-sm font-bold" for="review-source-url">{{ __('admin.homepage_reviews.source_url') }}<input id="review-source-url" name="source_url" value="{{ old('source_url', $review->source_url) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" aria-describedby="review-source-url-help"><span id="review-source-url-help" class="mt-2 block text-xs font-normal text-easytire-muted">{{ __('admin.homepage_reviews.source_url_hint') }}</span></label>
            </section>
            @foreach (['nb' => __('admin.homepage_reviews.locale_nb'), 'ru' => __('admin.homepage_reviews.locale_ru')] as $locale => $localeLabel)
                <section class="easytire-card space-y-5 rounded-2xl p-5 sm:p-6"><h2 class="text-lg font-black">{{ $localeLabel }}</h2>
                    @foreach (['quote' => 'textarea', 'author' => 'text', 'source_label' => 'text'] as $name => $type)
                        @php($field = $name.'_'.$locale)
                        <label class="block text-sm font-bold" for="review-{{ $field }}">{{ __('admin.homepage_reviews.'.$name) }}
                            @if ($type === 'textarea')<textarea id="review-{{ $field }}" name="{{ $field }}" rows="4" class="easytire-input mt-2 w-full rounded-lg p-3">{{ old($field, $review->{$field}) }}</textarea>
                            @else<input id="review-{{ $field }}" name="{{ $field }}" value="{{ old($field, $review->{$field}) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">@endif
                        </label>
                    @endforeach
                </section>
            @endforeach
            <button class="min-h-11 rounded-lg bg-easytire-brand px-5 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong" type="submit">{{ __('admin.homepage_reviews.save') }}</button>
        </form>
        @if ($review->exists)<form method="POST" action="{{ route('admin.homepage-reviews.destroy', $review) }}">@csrf @method('DELETE')<button class="min-h-11 text-sm font-semibold text-red-700 underline decoration-2 underline-offset-4" type="submit">{{ __('admin.homepage_reviews.delete') }}</button></form>@endif
    </div>
</x-layouts.admin>
