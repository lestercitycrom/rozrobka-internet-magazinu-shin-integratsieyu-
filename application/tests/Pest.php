<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Security');

function ndiFixture(string $name): string
{
    return base_path('tests/Fixtures/Ndi/'.$name);
}
