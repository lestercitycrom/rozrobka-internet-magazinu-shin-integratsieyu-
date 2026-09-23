<x-layouts.admin :title="__('admin.homepage_partners.title')">
    <div class="mx-auto max-w-4xl space-y-6">
        <a class="inline-flex min-h-11 items-center text-sm font-semibold text-easytire-brand underline decoration-2 underline-offset-4" href="{{ route('admin.homepage-business-partners.index') }}">← {{ __('admin.homepage_partners.title') }}</a>
        <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $partner->exists ? __('admin.homepage_partners.edit') : __('admin.homepage_partners.new') }}</h1>
        <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ __('admin.homepage_partners.rights_hint') }}</p>
        @if (session('status'))<p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800" role="status">{{ session('status') }}</p>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ $partner->exists ? route('admin.homepage-business-partners.update', $partner) : route('admin.homepage-business-partners.store') }}" class="space-y-6" data-dirty-form>
            @csrf @if($partner->exists) @method('PUT') @endif
            <section class="easytire-card grid gap-5 rounded-2xl p-5 md:grid-cols-2">
                <label class="text-sm font-bold" for="partner-name">{{ __('admin.homepage_partners.name') }}<input id="partner-name" required name="name" value="{{ old('name', $partner->name) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3"></label>
                <label class="text-sm font-bold" for="partner-status">{{ __('admin.homepage_partners.status') }}<select id="partner-status" name="publication_status" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('publication_status', $partner->publication_status?->value ?? 'draft') === $status->value)>{{ __('content_ui.status_'.$status->value) }}</option>@endforeach</select></label>
                <label class="text-sm font-bold" for="partner-asset">{{ __('admin.homepage_partners.asset') }}<select id="partner-asset" name="media_asset_id" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3"><option value="">{{ __('admin.homepage_partners.choose_asset') }}</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected((string) old('media_asset_id', $partner->media_asset_id) === (string) $asset->id)>{{ $asset->original_name }} · {{ $asset->alt_nb ?: $asset->path }}</option>@endforeach</select><span class="mt-2 block text-xs font-normal text-easytire-muted">{{ __('admin.homepage_partners.asset_hint') }}</span></label>
                <label class="text-sm font-bold" for="partner-url">{{ __('admin.homepage_partners.website_url') }}<input id="partner-url" name="website_url" value="{{ old('website_url', $partner->website_url) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3" inputmode="url"></label>
                <label class="text-sm font-bold" for="partner-order">{{ __('admin.homepage_partners.order') }}<input id="partner-order" required type="number" min="0" name="sort_order" value="{{ old('sort_order', $partner->sort_order ?? 0) }}" class="easytire-input mt-2 min-h-11 w-full rounded-lg px-3"></label>
            </section>
            <button class="et-btn et-btn--primary" type="submit">{{ __('admin.homepage_partners.save') }}</button>
        </form>
        @if($partner->exists)<form method="POST" action="{{ route('admin.homepage-business-partners.destroy', $partner) }}">@csrf @method('DELETE')<button class="min-h-11 text-sm font-semibold text-red-700 underline decoration-2 underline-offset-4" type="submit">{{ __('admin.homepage_partners.delete') }}</button></form>@endif
    </div>
</x-layouts.admin>
