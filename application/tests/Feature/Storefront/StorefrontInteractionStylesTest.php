<?php

test('storefront interactive cards stay anchored and remain keyboard accessible', function (): void {
    $styles = file_get_contents(resource_path('css/app.css'));
    $home = file_get_contents(resource_path('views/welcome.blade.php'));

    expect($styles)
        ->toContain('.easytire-interactive:focus-visible')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->not->toContain('transform: translateY');

    expect($home)
        ->toContain('easytire-card easytire-interactive')
        ->not->toContain('hover:-translate-y');
});

test('shared red design tokens keep white labels at wcag aa contrast', function (): void {
    $styles = file_get_contents(resource_path('css/app.css'));
    $layout = file_get_contents(resource_path('views/components/layouts/storefront.blade.php'));

    expect($styles)
        ->toContain('--easytire-brand: #c90000;')
        ->toContain('--easytire-brand-strong: #920000;')
        ->toContain('rgb(201 0 0 / 0.14)')
        ->not->toContain('--easytire-brand: #ff0000;');

    expect($layout)->toContain('<meta name="theme-color" content="#101010">');
});
