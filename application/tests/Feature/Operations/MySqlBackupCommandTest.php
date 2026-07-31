<?php

use Illuminate\Support\Facades\Artisan;

test('mysql backup command refuses non mysql connections', function () {
    config()->set('database.default', 'sqlite');

    expect(Artisan::call('easytire:backup:mysql'))->toBe(1);
});
