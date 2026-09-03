<?php

use Illuminate\Support\Facades\File;

test('storefront blade templates contain no static inline presentation', function (): void {
    $occurrences = collect(File::allFiles(resource_path('views/storefront')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->flatMap(function (SplFileInfo $file): array {
            preg_match_all('/style\s*=/i', File::get($file->getPathname()), $matches);

            return array_fill(0, count($matches[0]), $file->getPathname());
        });

    expect($occurrences)->toBeEmpty();
});

test('sharp geometry is token and component owned without a wildcard repair cascade', function (): void {
    $app = File::get(resource_path('css/app.css'));
    $tokens = File::get(resource_path('css/design-tokens.css'));
    $contracts = File::get(resource_path('css/storefront-contracts.css'));
    $components = File::get(resource_path('css/pro-design.css'));
    $storefront = File::get(resource_path('css/storefront-v3.css'));

    expect(File::exists(resource_path('css/storefront-shapes.css')))->toBeFalse()
        ->and($app)
        ->toContain("@import './pro-design.css';")
        ->toContain("@import './storefront-v3.css';")
        ->toContain("@import './storefront-contracts.css';")
        ->not->toContain('storefront-shapes.css')
        ->and(strpos($app, "@import './storefront-contracts.css';"))
        ->toBeGreaterThan(strpos($app, "@import './storefront-v3.css';"))
        ->and($tokens)
        ->toContain('--et-radius-media:')
        ->toContain('--et-radius-drawer:')
        ->toContain('--et-radius-functional-circle:')
        ->toContain('--et-radius-map-pin:')
        ->and($contracts)
        ->toContain('--et-radius-control: var(--et-radius-sharp);')
        ->toContain('--et-radius-media: var(--et-radius-sharp);')
        ->toContain('--et-radius-drawer: var(--et-radius-sharp);')
        ->not->toMatch('/!\s*important\s*[;}]/')
        ->not->toMatch('/data-storefront-style[^\{]*\*\s*(?:,|\{)/')
        ->and($components)
        ->toContain(".storefront input[type='radio'] { border-radius: var(--et-radius-functional-circle); }")
        ->toContain('border-radius: var(--et-radius-map-pin);')
        ->toContain('border-radius: var(--et-radius-drawer) var(--et-radius-drawer) 0 0;')
        ->and($storefront)
        ->toContain('border-radius: var(--et-radius-control);')
        ->toContain('box-shadow: var(--et-storefront-surface-shadow')
        ->toContain('border: 1px solid var(--v3-line);');
});

test('the retained inline presentation inventory is explicit and empty', function (): void {
    $inventory = File::get(base_path('../evidence/issue-33/ownership-inventory-before.md'));

    expect($inventory)
        ->toContain('Total storefront occurrences: **98**')
        ->toContain('No value is computed from customer, catalogue, payment or other runtime data.');
});
