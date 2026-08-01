<?php

test('every static storefront translation used by catalog views exists in NB and RU', function (): void {
    $catalogViews = collect(glob(resource_path('views/storefront/catalog/*.blade.php')));
    $keys = $catalogViews
        ->flatMap(function (string $path): array {
            $contents = file_get_contents($path);
            expect($contents)->not->toMatch('/(?:__|trans_choice)\(\s*[\'\"]storefront\.\s*[\'\"]\s*\./');

            preg_match_all('/(?:__|trans_choice)\(\s*[\'\"]storefront\.([a-z0-9_]+)[\'\"]/', $contents, $matches);

            return $matches[1];
        })
        ->unique()
        ->values();

    expect($keys)->not->toBeEmpty()->toContain('bolt_holes', 'has_image');

    foreach (['nb', 'ru'] as $locale) {
        $translations = require lang_path($locale.'/storefront.php');
        $missing = $keys->reject(fn (string $key): bool => array_key_exists($key, $translations))->values()->all();

        expect($missing, "Missing {$locale} storefront catalog translations")->toBe([]);
    }
});
