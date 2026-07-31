<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="EasyTire i Tromsø – grunnlag for dekk, felg og dekktjenester.">
    <title>EasyTire | Dekk og felg i Tromsø</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-950 antialiased">
    <a href="#innhold" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-slate-950 focus:shadow-lg">Hopp til innhold</a>
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="/" class="text-xl font-bold tracking-tight" aria-label="EasyTire, forsiden">EasyTire</a>
            <nav aria-label="Hovednavigasjon" class="hidden items-center gap-7 text-sm font-medium text-slate-700 md:flex" data-testid="desktop-navigation">
                <a href="#dekk-og-felg" class="rounded focus:outline-2 focus:outline-offset-4 focus:outline-sky-700">Dekk og felg</a>
                <a href="#tjenester" class="rounded focus:outline-2 focus:outline-offset-4 focus:outline-sky-700">Tjenester</a>
            </nav>
            <a href="#dekk-og-felg" class="rounded-full bg-sky-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-800 focus:outline-2 focus:outline-offset-4 focus:outline-sky-700">Utforsk dekk</a>
        </div>
        <nav aria-label="Hurtiglenker" class="border-t border-slate-100 px-5 py-3 md:hidden" data-testid="mobile-navigation">
            <div class="mx-auto flex max-w-6xl gap-5 overflow-x-auto text-sm font-medium text-slate-700">
                <a href="#dekk-og-felg" class="shrink-0">Dekk og felg</a>
                <a href="#tjenester" class="shrink-0">Tjenester</a>
            </div>
        </nav>
    </header>
    <main id="innhold">
        <section class="bg-slate-950 text-white">
            <div class="mx-auto grid max-w-6xl gap-10 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-[1.2fr_0.8fr] lg:items-end">
                <div>
                    <p class="mb-5 text-sm font-semibold tracking-[0.18em] text-sky-300 uppercase">Tromsø</p>
                    <h1 class="max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">Dekk og felg – på din måte</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">EasyTire bygger en enklere vei til dekk, felg og dekktjenester. Se etter riktig løsning når katalogen er klar, eller start med tjenestene våre.</p>
                </div>
                <aside class="rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl" aria-label="Neste steg">
                    <p class="text-sm font-semibold text-sky-300">På vei</p>
                    <p class="mt-2 text-xl font-semibold">Søk etter dekk og felg</p>
                    <p class="mt-2 text-sm leading-6 text-slate-300">Katalog og produktinformasjon blir lagt til når de er klare for publisering.</p>
                </aside>
            </div>
        </section>
        <section id="dekk-og-felg" class="mx-auto max-w-6xl scroll-mt-6 px-5 py-16 sm:px-8 sm:py-24" aria-labelledby="catalog-heading" data-testid="catalog-foundation">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold tracking-[0.16em] text-sky-800 uppercase">Dekk og felg</p>
                <h2 id="catalog-heading" class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Finn riktig utgangspunkt for bilen din</h2>
                <p class="mt-4 text-lg leading-8 text-slate-600">Her kommer søk og katalog for dekk, felg og komplette hjulpakker. Vi viser ikke produkter eller priser før dataene er klare.</p>
            </div>
            <form class="mt-9 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-describedby="catalog-help">
                <label for="future-search" class="text-base font-semibold">Hva ser du etter?</label>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                    <input id="future-search" name="future-search" type="search" placeholder="For eksempel dekk, felg eller størrelse" disabled class="min-h-12 w-full rounded-lg border border-slate-300 bg-slate-100 px-4 text-slate-700 placeholder:text-slate-500 disabled:cursor-not-allowed" aria-disabled="true">
                    <button type="button" disabled class="min-h-12 rounded-lg bg-slate-300 px-5 font-semibold text-slate-600 disabled:cursor-not-allowed">Søk kommer snart</button>
                </div>
                <p id="catalog-help" class="mt-3 text-sm text-slate-600">Søket er klargjort som en plass for den kommende katalogen.</p>
            </form>
        </section>
        <section id="tjenester" class="scroll-mt-6 border-y border-slate-200 bg-white" aria-labelledby="services-heading" data-testid="services-section">
            <div class="mx-auto max-w-6xl px-5 py-16 sm:px-8 sm:py-24">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold tracking-[0.16em] text-sky-800 uppercase">Tjenester</p>
                    <h2 id="services-heading" class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Tjenester som er en del av EasyTire</h2>
                    <p class="mt-4 text-lg leading-8 text-slate-600">Tilgjengelighet, betingelser og bestilling avklares før de publiseres som aktive tilbud.</p>
                </div>
                <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
                    @foreach (['Mobilt dekkskift', 'Dekkskift på dekkverksted', 'Tesla og elbiler', 'Bobil og varebil', 'Dekkhotell', 'Dekk, felg og komplette hjulpakker'] as $service)
                        <li class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                            <h3 class="font-semibold">{{ $service }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Informasjon og neste steg kommer når tjenesten er klar for publisering.</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    </main>
    <footer class="bg-slate-950 px-5 py-8 text-sm text-slate-300 sm:px-8">
        <div class="mx-auto flex max-w-6xl flex-col justify-between gap-2 sm:flex-row"><p>EasyTire · Tromsø</p><p>Offentlig informasjon oppdateres etter hvert som innhold og katalog blir klare.</p></div>
    </footer>
</body>
</html>
