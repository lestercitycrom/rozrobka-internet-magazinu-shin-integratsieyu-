@php
    $values = fn (string $field) => old($field, match ($field) {
        'markup_percent' => ($rule->markup_basis_points ?? 0) / 100,
        'fixed_markup_nok' => ($rule->fixed_markup_minor ?? 0) / 100,
        'rounding_nok' => ($rule->rounding_increment_minor ?? 1) / 100,
        default => $rule->$field,
    });
@endphp

<x-layouts.admin :title="__('pricing.title')">
    <div class="max-w-4xl space-y-8">
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('pricing.title') }}</h1>
            <p class="mt-3 max-w-3xl leading-7 text-easytire-muted">{{ __('pricing.intro') }}</p>
        </header>

        <p class="rounded-xl border-l-4 border-easytire-brand bg-easytire-brand-soft px-4 py-3 text-sm leading-6 text-easytire-ink">{{ __('pricing.warning') }}</p>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ $rule->exists ? route('admin.pricing-rules.update', $rule) : route('admin.pricing-rules.store') }}" class="easytire-card space-y-8 rounded-2xl p-5 sm:p-8">
            @csrf
            @if ($rule->exists) @method('PUT') @endif

            <fieldset class="grid gap-5 sm:grid-cols-2">
                <legend class="text-lg font-black">{{ __('pricing.title') }}</legend>
                @foreach (['name', 'source_price_list_code', 'currency', 'scope_key'] as $field)
                    <div @class(['sm:col-span-2' => $field === 'name'])>
                        <label for="{{ $field }}" class="block text-sm font-bold">{{ __('pricing.'.$field) }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ $values($field) }}" @required(in_array($field, ['name', 'source_price_list_code', 'currency'])) @if($field === 'currency') maxlength="3" autocapitalize="characters" @endif class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm @error($field) border-red-600 @enderror" aria-describedby="{{ $field }}-help @error($field) {{ $field }}-error @enderror">
                        @if ($field === 'scope_key') <p id="{{ $field }}-help" class="mt-2 text-sm text-easytire-muted">{{ __('pricing.scope_key_required') }}</p> @else <span id="{{ $field }}-help" class="sr-only">{{ __('pricing.'.$field) }}</span> @endif
                        @error($field)<p id="{{ $field }}-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div>
                    <label for="scope" class="block text-sm font-bold">{{ __('pricing.scope') }}</label>
                    <select id="scope" name="scope" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm @error('scope') border-red-600 @enderror" aria-describedby="scope-error">@foreach ($scopes as $scope)<option value="{{ $scope->value }}" @selected(old('scope', $rule->scope?->value ?? 'global') === $scope->value)>{{ $scope->value }}</option>@endforeach</select>
                    @error('scope')<p id="scope-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <label class="flex min-h-11 w-full items-center gap-3 rounded-lg border border-easytire-border bg-easytire-surface px-4 text-sm font-bold"><input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->is_active)) class="size-5 rounded border-easytire-border text-easytire-brand focus:ring-easytire-brand">{{ __('pricing.active') }}</label>
                </div>
            </fieldset>

            <fieldset class="border-t border-easytire-border pt-7"><legend class="text-lg font-black">{{ __('pricing.markup_percent') }}</legend><div class="mt-5 grid gap-5 sm:grid-cols-3">@foreach (['markup_percent', 'fixed_markup_nok', 'rounding_nok'] as $field)<div><label for="{{ $field }}" class="block text-sm font-bold">{{ __('pricing.'.$field) }}</label><input id="{{ $field }}" name="{{ $field }}" type="text" inputmode="decimal" value="{{ $values($field) }}" required class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm @error($field) border-red-600 @enderror" aria-describedby="{{ $field }}-error">@error($field)<p id="{{ $field }}-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror</div>@endforeach</div></fieldset>

            <fieldset class="border-t border-easytire-border pt-7"><legend class="text-lg font-black">{{ __('pricing.priority') }}</legend><div class="mt-5 grid gap-5 sm:grid-cols-3"><div><label for="priority" class="block text-sm font-bold">{{ __('pricing.priority') }}</label><input id="priority" name="priority" type="number" value="{{ $values('priority') }}" required class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm @error('priority') border-red-600 @enderror" aria-describedby="priority-error">@error('priority')<p id="priority-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror</div>@foreach (['starts_at', 'ends_at'] as $field)<div><label for="{{ $field }}" class="block text-sm font-bold">{{ __('pricing.'.$field) }}</label><input id="{{ $field }}" name="{{ $field }}" type="date" value="{{ $values($field) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3 text-sm @error($field) border-red-600 @enderror" aria-describedby="{{ $field }}-error">@error($field)<p id="{{ $field }}-error" class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror</div>@endforeach</div></fieldset>

            <div class="flex flex-wrap items-center gap-4 border-t border-easytire-border pt-6"><button class="min-h-11 rounded-lg bg-easytire-brand px-5 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong hover:shadow-md focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" type="submit">{{ __('pricing.save') }}</button><a class="min-h-11 rounded-lg px-4 py-3 text-sm font-bold text-easytire-muted underline underline-offset-4 hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" href="{{ route('admin.pricing-rules.index') }}">{{ __('pricing.title') }}</a></div>
        </form>
    </div>
</x-layouts.admin>
