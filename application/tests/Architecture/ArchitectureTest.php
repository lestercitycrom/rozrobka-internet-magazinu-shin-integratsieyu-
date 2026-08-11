<?php

arch('debugging helpers are not used in application code')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('application code does not depend on test code')
    ->expect('App')
    ->not->toUse('Tests');

test('staging content security policy permits only the approved Google Maps frame origins', function (): void {
    $nginx = file_get_contents(dirname(__DIR__, 3).'/deploy/staging-easytire.nginx.conf');

    expect($nginx)
        ->toContain('frame-src https://www.google.com https://maps.google.com https://www.google.no https://maps.google.no;')
        ->not->toContain('frame-src *');
});
