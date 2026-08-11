<x-layouts.admin :title="__('public_service_prices.title')">
    <div class="mx-auto max-w-4xl space-y-8">
        <header>
            <a class="inline-flex min-h-11 items-center text-sm font-semibold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route('admin.public-service-prices.index') }}">← {{ __('public_service_prices.title') }}</a>
            <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $price->exists ? $price->code : __('public_service_prices.new') }}</h1>
        </header>

        @if (session('status'))<p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif
        @if ($errors->any())<p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</p>@endif

        <form method="POST" action="{{ $price->exists ? route('admin.public-service-prices.update', $price) : route('admin.public-service-prices.store') }}" class="easytire-card space-y-7 rounded-2xl p-5 sm:p-8" data-dirty-form>
            @csrf
            @if ($price->exists) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ([
                    'code' => __('public_service_prices.code'),
                    'category_code' => __('public_service_prices.category_code'),
                    'category_title_nb' => __('public_service_prices.category_title_nb'),
                    'category_title_ru' => __('public_service_prices.category_title_ru'),
                    'title_nb' => __('public_service_prices.title_nb'),
                    'title_ru' => __('public_service_prices.title_ru'),
                ] as $field => $label)
                    <label class="block text-sm font-bold" for="price-{{ $field }}">{{ $label }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" id="price-{{ $field }}" name="{{ $field }}" value="{{ old($field, $price->{$field}) }}" required></label>
                @endforeach

                <label class="block text-sm font-bold" for="price-nok">{{ __('public_service_prices.price') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" id="price-nok" name="price_nok" inputmode="decimal" value="{{ old('price_nok', $price->exists ? number_format($price->price_minor / 100, 2, '.', '') : '') }}" required></label>
                <label class="block text-sm font-bold" for="price-order">{{ __('public_service_prices.order') }}<input class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" id="price-order" name="sort_order" type="number" value="{{ old('sort_order', $price->sort_order ?? 0) }}" required></label>
                @foreach (['description_nb' => __('public_service_prices.description_nb'), 'description_ru' => __('public_service_prices.description_ru')] as $field => $label)
                    <label class="block text-sm font-bold sm:col-span-2" for="price-{{ $field }}">{{ $label }}<textarea class="easytire-input mt-2 min-h-28 w-full rounded-lg px-3 py-2" id="price-{{ $field }}" name="{{ $field }}">{{ old($field, $price->{$field}) }}</textarea></label>
                @endforeach
            </div>

            <label class="flex min-h-11 items-center gap-3 rounded-lg border border-easytire-border bg-easytire-surface px-4 text-sm font-bold"><input class="size-5 rounded" type="checkbox" name="is_active" value="1" @checked(old('is_active', $price->is_active ?? true))>{{ __('public_service_prices.active') }}</label>
            <button class="min-h-11 rounded-lg bg-easytire-brand px-5 text-sm font-bold text-white" type="submit">{{ __('public_service_prices.save') }}</button>
        </form>

        @if ($price->exists)
            <form method="POST" action="{{ route('admin.public-service-prices.destroy', $price) }}">@csrf @method('DELETE')<button class="min-h-11 text-sm font-semibold text-red-700 underline decoration-2 underline-offset-4" type="submit">{{ __('public_service_prices.delete') }}</button></form>
        @endif
    </div>
</x-layouts.admin>
