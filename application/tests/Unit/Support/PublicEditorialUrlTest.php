<?php

use App\Support\PublicEditorialUrl;

test('editorial URLs accept safe internal paths and public HTTP destinations', function (): void {
    expect(PublicEditorialUrl::sanitize('/tjenester?type=hjul#valg'))->toBe('/tjenester?type=hjul#valg')
        ->and(PublicEditorialUrl::sanitize('https://example.test/tilbud'))->toBe('https://example.test/tilbud')
        ->and(PublicEditorialUrl::isExternal('https://example.test'))->toBeTrue();
});

test('editorial URLs reject protocol-relative script and control-character destinations', function (): void {
    expect(PublicEditorialUrl::sanitize('//evil.example.test'))->toBeNull()
        ->and(PublicEditorialUrl::sanitize('javascript:alert(1)'))->toBeNull()
        ->and(PublicEditorialUrl::sanitize("/kontakt\nInjected"))->toBeNull();
});

test('internal editorial URLs are localized exactly once for Russian QA', function (): void {
    expect(PublicEditorialUrl::localized('/kontakt?fra=hjem#skjema', 'ru'))->toBe('/ru/kontakt?fra=hjem#skjema')
        ->and(PublicEditorialUrl::localized('/ru/kontakt', 'ru'))->toBe('/ru/kontakt')
        ->and(PublicEditorialUrl::localized('/kontakt', 'nb'))->toBe('/kontakt')
        ->and(PublicEditorialUrl::localized('https://example.test/kontakt', 'ru'))->toBe('https://example.test/kontakt');
});
