<?php

test('storefront interactive cards stay anchored and remain keyboard accessible', function (): void {
    $styles = file_get_contents(resource_path('css/app.css'));
    $home = file_get_contents(resource_path('views/welcome.blade.php'));

    expect($styles)
        ->toContain('.easytire-interactive:hover')
        ->not->toContain('.easytire-interactive:focus-visible')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->not->toContain('transform: translateY');

    expect($home)
        ->toContain('et-v3-service-grid')
        ->toContain('et-v3-service')
        ->not->toContain('hover:-translate-y');
});

test('storefront and admin consume one shared design foundation', function (): void {
    $styles = file_get_contents(resource_path('css/app.css'));
    $tokens = file_get_contents(resource_path('css/design-tokens.css'));
    $layout = file_get_contents(resource_path('views/components/layouts/storefront.blade.php'));

    expect($tokens)
        ->toContain('--et-color-brand: #d20a0a;')
        ->toContain('--easytire-brand: var(--et-color-brand);')
        ->toContain('--v3-red: var(--et-color-brand);')
        ->toContain('--et-radius-control: .65rem;')
        ->toContain('--et-color-brand-accent:')
        ->toContain('--et-container-gutter:')
        ->toContain('--et-header-height: 82px;')
        ->toContain('--et-section-space:')
        ->toContain('--et-layout-gap:')
        ->toContain('--et-shadow-surface:')
        ->toContain('--et-header-background: #ffffff;')
        ->toContain('--et-header-background-scrolled: #ffffff;')
        ->toContain('--et-focus-color-light:')
        ->toContain('--et-focus-color-dark:')
        ->not->toContain('--et-color-brand-admin:')
        ->not->toContain('--et-color-brand-public:')
        ->not->toContain('--et-red: #')
        ->not->toContain('--et-color-brand-rgb:')
        ->not->toContain('--v3-red: #')
        ->not->toContain('--easytire-brand: #');

    expect($styles)
        ->toContain("@import './design-tokens.css';")
        ->toContain('outline: var(--et-focus-width) solid var(--et-focus-color);')
        ->toContain('box-shadow: none;')
        ->not->toContain('--easytire-brand: #ff0000;');

    expect($layout)
        ->toContain('<meta name="theme-color" content="#101010">')
        ->toContain('data-site-header')
        ->toContain('et-footer__logo-plate')
        ->not->toContain('et-footer__logo-lockup')
        ->toContain('data-footer-groups')
        ->toContain('data-footer-toggle')
        ->toContain('data-footer-links');

    $storefront = file_get_contents(resource_path('css/storefront-v3.css'));
    $contracts = file_get_contents(resource_path('css/storefront-contracts.css'));
    $promotionPartial = file_get_contents(resource_path('views/storefront/_homepage-promotions.blade.php'));

    expect(substr_count($storefront, '.et-v3-process-strip {'))->toBe(1)
        ->and($storefront)->toContain('.et-v3-promotion-grid:has(> .et-v3-promotion:nth-child(2):last-child)')
        ->and($storefront)->not->toContain('var(--et-color-brand-rgb)')
        ->and($storefront)
        ->toContain('.et-v3-promotion:hover .et-v3-promotion__details')
        ->toContain('.et-v3-promotion:focus-within .et-v3-promotion__details')
        ->toContain('.et-v3-promotion__details.is-open')
        ->toContain('.storefront .et-btn--outline:hover')
        ->toContain("[aria-disabled='true']")
        ->toContain('.storefront .et-footer.is-enhanced .et-footer__toggle')
        ->toContain('@media (hover: none), (max-width: 767px)')
        ->and($promotionPartial)
        ->toContain('class="et-v3-promotion__details"')
        ->toContain('data-promotion-disclosure')
        ->and($contracts)->toContain('.et-admin-body')
        ->and($contracts)->toContain('--radius-2xl: var(--et-radius-sharp);');
});
