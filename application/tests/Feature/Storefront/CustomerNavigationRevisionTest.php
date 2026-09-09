<?php

use Database\Seeders\ContentPageSeeder;
use Database\Seeders\SeoServicePageSeeder;
use Database\Seeders\ServicePageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return list<DOMNode> */
function issue45Nodes(string $html, string $xpath): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $nodes = (new DOMXPath($document))->query($xpath);

    return $nodes === false ? [] : iterator_to_array($nodes);
}

function issue45Texts(string $html, string $xpath): array
{
    return array_map(
        static fn (DOMNode $node): string => trim((string) preg_replace('/\s+/u', ' ', $node->textContent)),
        issue45Nodes($html, $xpath),
    );
}

/** @return list<string> */
function issue45Hrefs(string $html, string $xpath): array
{
    return array_values(array_filter(array_map(
        static fn (DOMNode $node): ?string => $node->attributes?->getNamedItem('href')?->nodeValue,
        issue45Nodes($html, $xpath),
    )));
}

beforeEach(function (): void {
    $this->seed([ContentPageSeeder::class, ServicePageSeeder::class, SeoServicePageSeeder::class]);
});

test('Norwegian header and footer follow the exact customer navigation contract', function (): void {
    $response = $this->get('/')->assertOk();
    $html = $response->getContent();

    expect(issue45Texts($html, "//nav[@data-testid='desktop-navigation']/*[self::a] | //nav[@data-testid='desktop-navigation']/div[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__item ')]/span[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__trigger ')]/a"))->toBe([
        'Tjenester', 'Dekk og felger', 'Priser', 'Aktuelle tilbud', 'Om Oss', 'Kontakt',
    ])->and(issue45Texts($html, "//div[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__mega-grid ')]//h2"))->toBe(['Dekk og hjul', 'Biltjenester'])
        ->and(issue45Texts($html, "//div[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__mega-grid ')]/section[1]//li/a"))->toBe([
            'Dekkskift hjemme hos deg', 'Dekkskift på verksted', 'Dekkhotell', 'Montering og balansering',
            'Punkteringsreparasjon', 'TPMS', 'Dekkskift for Tesla og elbil', 'Bobil og varebil',
        ])->and(issue45Texts($html, "//div[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__mega-grid ')]/section[2]//li/a"))->toBe([
            'Oljeskift', 'Interiørrens', 'Polering og lakkorrigering', 'Batteritest og batteribytte',
            'Bytte av lyspærer og vindusviskere', 'Utelåsing – åpning av bil',
        ])->and(issue45Texts($html, "//ul[@id='footer-services-links']/li/a"))->toBe([
            'Dekkskift hjemme hos deg', 'Dekkhotell', 'Dekkskift på verksted', 'Oljeskift', 'Bilpleie',
            'Dekkskift for Tesla og elbil', 'Alle tjenester', 'Priser',
        ])->and(issue45Texts($html, "//ul[@id='footer-store-links']/li/a"))->toBe([
            'Finn etter bil', 'Dekk', 'Felger', 'Komplette hjul', 'Handlekurv',
        ])->and(issue45Texts($html, "//ul[@id='footer-company-links']/li/a"))->toBe([
            'Om oss', 'Kontakt', 'Personvern', 'Kjøpsvilkår', 'Angreskjema (PDF)',
        ]);

    $response
        ->assertSee('data-testid="header-call"', false)
        ->assertSee('href="tel:+4793958270"', false)
        ->assertDontSee('data-testid="header-booking"', false)
        ->assertSee('data-testid="footer-booking"', false)
        ->assertSee('Bestill dekkskift')
        ->assertSee('href="'.route('storefront.home').'#aktuelle-tilbud"', false)
        ->assertSee('id="aktuelle-tilbud"', false)
        ->assertSee('Din lokale partner for dekkskift, dekkhotell og bilservice i Tromsø.')
        ->assertSee('EasyTask AS · Org.nr. 934 860 187 MVA')
        ->assertDontSee('et-footer__logo-lockup', false)
        ->assertSee('src="/brand/easytire-logo.png"', false)
        ->assertSee('data-catalog-autocomplete="'.route('storefront.tires.suggestions').'"', false);

    expect(file_exists(public_path('dokumenter/angreskjema-easytire.pdf')))->toBeTrue();
});

test('hidden Russian projection keeps navigation and footer parity', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    $response = $this->get('/ru')->assertOk();
    $html = $response->getContent();

    expect(issue45Texts($html, "//nav[@data-testid='desktop-navigation']/*[self::a] | //nav[@data-testid='desktop-navigation']/div[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__item ')]/span[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__trigger ')]/a"))->toBe([
        'Услуги', 'Шины и диски', 'Цены', 'Актуальные предложения', 'О нас', 'Контакты',
    ])->and(issue45Texts($html, "//div[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__mega-grid ')]//h2"))->toBe(['Шины и колёса', 'Автомобильные услуги'])
        ->and(issue45Texts($html, "//div[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__mega-grid ')]/section[1]//li/a"))->toBe([
            'Замена колёс у вас', 'Замена колёс в мастерской', 'Хранение колёс', 'Монтаж и балансировка',
            'Ремонт прокола', 'TPMS', 'Замена колёс для Tesla и электромобилей', 'Автодома и фургоны',
        ])->and(issue45Texts($html, "//div[contains(concat(' ', normalize-space(@class), ' '), ' et-nav__mega-grid ')]/section[2]//li/a"))->toBe([
            'Замена масла', 'Химчистка салона', 'Полировка и коррекция лакокрасочного покрытия',
            'Проверка и замена аккумулятора', 'Замена ламп и стеклоочистителей',
            'Открытие заблокированного автомобиля',
        ])->and(issue45Texts($html, "//ul[@id='footer-services-links']/li/a"))->toBe([
            'Замена колёс у вас', 'Хранение колёс', 'Замена колёс в мастерской', 'Замена масла',
            'Уход за автомобилем', 'Замена колёс для Tesla и электромобилей', 'Все услуги', 'Цены',
        ])
        ->and(issue45Texts($html, "//ul[@id='footer-store-links']/li/a"))->toBe([
            'Подбор по автомобилю', 'Шины', 'Диски', 'Колёса в сборе', 'Корзина',
        ])->and(issue45Texts($html, "//ul[@id='footer-company-links']/li/a"))->toBe([
            'О нас', 'Контакты', 'Конфиденциальность', 'Условия покупки', 'Форма отказа (PDF)',
        ]);

    $response
        ->assertSee('data-testid="header-call"', false)
        ->assertSee('href="tel:+4793958270"', false)
        ->assertDontSee('data-testid="header-booking"', false)
        ->assertSee('data-testid="footer-booking"', false)
        ->assertSee('href="'.route('storefront.preview.home').'#aktuelle-tilbud"', false)
        ->assertSee('href="'.route('storefront.preview.pricing.index').'"', false)
        ->assertSee('data-catalog-autocomplete="'.route('storefront.preview.tires.suggestions').'"', false)
        ->assertSee('Русская QA-проекция');
});

test('all customer navigation destinations resolve without placeholders or external work', function (): void {
    foreach (['/', '/ru'] as $path) {
        if ($path === '/ru') {
            config()->set('storefront.ru_preview_enabled', true);
        }

        $html = $this->get($path)->assertOk()->getContent();
        $hrefs = issue45Hrefs($html, "//nav[@data-testid='desktop-navigation']//a | //div[@data-testid='mobile-navigation']//a | //footer//a");

        foreach (array_unique($hrefs) as $href) {
            expect($href)->not->toBe('#');
            if (str_starts_with($href, 'tel:') || str_starts_with($href, 'mailto:') || str_contains($href, 'maps.')) {
                continue;
            }
            if (parse_url($href, PHP_URL_PATH) === '/dokumenter/angreskjema-easytire.pdf') {
                continue;
            }

            $target = parse_url($href, PHP_URL_PATH) ?: '/';
            $query = parse_url($href, PHP_URL_QUERY);
            $response = $this->get($target.($query ? '?'.$query : ''));
            expect($response->status(), $href)->toBeLessThan(400);
        }
    }
});

test('shared quick finder uses required supported selections and preserves query values', function (): void {
    config()->set('storefront.ru_preview_enabled', true);
    foreach (['/', '/dekk-og-felger', '/ru', '/ru/dekk-og-felger'] as $path) {
        $response = $this->get($path.'?width=205&aspect=55&rim=16')->assertOk();
        $html = $response->getContent();

        $width = issue45Nodes($html, "//*[@data-testid='quick-finder']//select[@name='width']");
        expect($width)->toHaveCount(1)
            ->and(issue45Nodes($html, "//*[@data-testid='quick-finder']//select[@name='aspect']"))->toHaveCount(1)
            ->and(issue45Nodes($html, "//*[@data-testid='quick-finder']//select[@name='rim']"))->toHaveCount(1)
            ->and(issue45Nodes($html, "//*[@data-testid='quick-finder']//input[@name='width']"))->toHaveCount(0)
            ->and(issue45Nodes($html, "//select[@name='width']/option[@selected and @value='205']"))->toHaveCount(1)
            ->and(issue45Nodes($html, "//select[@name='aspect']/option[@selected and @value='55']"))->toHaveCount(1)
            ->and(issue45Nodes($html, "//select[@name='rim']/option[@selected and @value='16']"))->toHaveCount(1)
            ->and($width[0]->firstChild?->attributes?->getNamedItem('value')?->nodeValue)->toBe('')
            ->and($width[0]->attributes?->getNamedItem('required'))->not->toBeNull();
    }
});

test('missing standalone car-care pages have real distinct service-directory destinations', function (): void {
    $response = $this->get('/tjenester')->assertOk();

    $response
        ->assertSee('id="bilpleie"', false)
        ->assertSee('id="service-interior-cleaning"', false)
        ->assertSee('id="service-polishing-paint"', false)
        ->assertSee('id="service-lockout"', false)
        ->assertSee('href="'.route('storefront.content.show', 'kontakt').'"', false)
        ->assertSee('href="tel:+4793958270"', false);
});
