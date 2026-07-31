<x-layouts.storefront :title="$page->metaTitle ?? ('EasyTire | '.$page->title)" :description="$page->metaDescription">
    <article class="mx-auto max-w-4xl px-5 py-12 sm:px-8">
        <h1 class="text-4xl font-black">{{ $page->title }}</h1>
        @if($page->hero)<img data-testid="page-hero" src="{{ $page->hero['url'] }}" alt="{{ $page->hero['alt'] }}" loading="lazy" class="mt-6 aspect-video w-full rounded-xl object-cover">@endif
        @if ($page->excerpt)<p class="mt-5 text-xl text-easytire-muted">{{ $page->excerpt }}</p>@endif
        @if ($page->body)<div class="prose mt-8 max-w-none">{!! nl2br(e($page->body)) !!}</div>@endif
    </article>
</x-layouts.storefront>
