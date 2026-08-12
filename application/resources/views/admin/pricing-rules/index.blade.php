<x-layouts.admin :title="__('pricing.title')">
    <div class="space-y-8">
        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-easytire-brand">EasyTire</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ __('pricing.title') }}</h1>
                <p class="mt-3 max-w-2xl leading-7 text-easytire-muted">{{ __('pricing.intro') }}</p>
            </div>
            <a class="inline-flex min-h-11 items-center justify-center rounded-lg bg-easytire-brand px-5 text-sm font-bold text-white shadow-sm transition hover:bg-easytire-brand-strong hover:shadow-md focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" href="{{ route('admin.pricing-rules.create') }}">{{ __('pricing.new') }}</a>
        </header>

        @if ($rules->isEmpty())
            <div class="rounded-2xl border border-dashed border-easytire-border bg-white px-6 py-12 text-center text-easytire-muted"><p>{{ __('pricing.draft') }}</p></div>
        @else
            <div class="space-y-3 md:hidden">
                @foreach ($rules as $rule)
                    <article class="easytire-card rounded-2xl p-5">
                        <div class="flex items-start justify-between gap-4"><h2 class="font-black">{{ $rule->name }}</h2><span @class(['rounded-full px-3 py-1 text-xs font-bold', 'bg-red-100 text-red-800' => $rule->is_active, 'bg-slate-100 text-slate-700' => ! $rule->is_active])>{{ $rule->is_active ? __('pricing.active') : __('pricing.draft') }}</span></div>
                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm"><div><dt class="text-easytire-muted">{{ __('pricing.scope') }}</dt><dd class="mt-1 font-bold">{{ $rule->scope->value }}</dd></div><div><dt class="text-easytire-muted">{{ __('pricing_tax.source_tax_mode') }}</dt><dd class="mt-1 font-bold">{{ __('pricing_tax.tax_modes.'.$rule->source_tax_mode->value) }}</dd></div><div><dt class="text-easytire-muted">{{ __('pricing.priority') }}</dt><dd class="mt-1 font-bold">{{ $rule->priority }}</dd></div></dl>
                        <a class="mt-5 inline-flex min-h-11 items-center rounded underline decoration-2 underline-offset-4 hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" href="{{ route('admin.pricing-rules.edit', $rule) }}">{{ __('pricing.edit') }}</a>
                    </article>
                @endforeach
            </div>

            <div class="easytire-card hidden overflow-x-auto rounded-2xl md:block">
                <table class="min-w-full text-left text-sm"><thead class="bg-easytire-ink text-white"><tr><th class="p-4 font-bold">{{ __('pricing.name') }}</th><th class="p-4 font-bold">{{ __('pricing.scope') }}</th><th class="p-4 font-bold">{{ __('pricing_tax.source_tax_mode') }}</th><th class="p-4 font-bold">{{ __('pricing.priority') }}</th><th class="p-4 font-bold">{{ __('pricing.active') }}</th><th class="p-4"><span class="sr-only">{{ __('pricing.edit') }}</span></th></tr></thead><tbody class="divide-y divide-easytire-border">@foreach ($rules as $rule)<tr class="transition hover:bg-easytire-brand-soft/50"><td class="p-4 font-bold">{{ $rule->name }}</td><td class="p-4 text-easytire-muted">{{ $rule->scope->value }}</td><td class="p-4">{{ __('pricing_tax.tax_modes.'.$rule->source_tax_mode->value) }}</td><td class="p-4 font-bold">{{ $rule->priority }}</td><td class="p-4"><span @class(['rounded-full px-3 py-1 text-xs font-bold', 'bg-red-100 text-red-800' => $rule->is_active, 'bg-slate-100 text-slate-700' => ! $rule->is_active])>{{ $rule->is_active ? __('pricing.active') : __('pricing.draft') }}</span></td><td class="p-4 text-right"><a class="rounded underline decoration-2 underline-offset-4 hover:text-easytire-brand focus:outline-2 focus:outline-offset-4 focus:outline-easytire-brand" href="{{ route('admin.pricing-rules.edit', $rule) }}">{{ __('pricing.edit') }}</a></td></tr>@endforeach</tbody></table>
            </div>
            <div class="pt-1">{{ $rules->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
