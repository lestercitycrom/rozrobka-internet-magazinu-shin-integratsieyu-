<x-layouts.admin :title="__('service_addons.title')">
    <div class="min-w-0 space-y-8">
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('service_addons.title') }}</h1>
            <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('service_addons.intro') }}</p>
        </header>

        <div class="easytire-card w-full max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl md:max-w-full">
            <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain" tabindex="0" role="region" aria-label="{{ __('service_addons.table_label') }}">
                <table class="w-full min-w-[54rem] text-left text-sm">
                <thead class="bg-easytire-ink text-white">
                    <tr>
                        <th class="p-4" scope="col">{{ __('service_addons.code') }}</th>
                        <th class="p-4" scope="col">{{ __('service_addons.title_nb') }}</th>
                        <th class="p-4" scope="col">{{ __('service_addons.price_nok') }}</th>
                        <th class="p-4" scope="col">{{ __('service_addons.applicability') }}</th>
                        <th class="p-4" scope="col">{{ __('service_addons.active') }}</th>
                        <th class="p-4" scope="col">{{ __('service_addons.edit') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-easytire-border">
                    @forelse ($addons as $addon)
                        <tr class="transition hover:bg-easytire-brand-soft/50">
                            <td class="p-4 font-bold">{{ $addon->code }}</td>
                            <td class="p-4">{{ $addon->title_nb }}</td>
                            <td class="p-4 font-bold">{{ $addon->price_minor === null ? __('service_addons.unpriced') : number_format($addon->price_minor / 100, 2, ',', ' ').' NOK' }}</td>
                            <td class="p-4 text-easytire-muted">{{ __('service_addons.applicability_'.$addon->applicability->value) }}</td>
                            <td class="p-4">
                                <span @class([
                                    'rounded-full px-3 py-1 text-xs font-bold',
                                    'bg-green-100 text-green-800' => $addon->is_active && $addon->price_minor > 0,
                                    'bg-red-100 text-red-800' => ! $addon->is_active || $addon->price_minor === null,
                                ])>
                                    {{ $addon->is_active && $addon->price_minor > 0 ? __('service_addons.active_status') : ($addon->price_minor === null ? __('service_addons.unpriced') : __('service_addons.inactive')) }}
                                </span>
                            </td>
                            <td class="p-4 text-right"><a class="rounded font-semibold underline decoration-2 underline-offset-4 hover:text-easytire-brand" href="{{ route('admin.service-addons.edit', $addon) }}">{{ __('service_addons.edit') }}</a></td>
                        </tr>
                    @empty
                        <tr><td class="p-6 text-easytire-muted" colspan="6">{{ __('service_addons.empty') }}</td></tr>
                    @endforelse
                </tbody>
                </table>
            </div>
        </div>

        {{ $addons->links() }}
    </div>
</x-layouts.admin>
