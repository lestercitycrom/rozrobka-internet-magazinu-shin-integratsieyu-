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
