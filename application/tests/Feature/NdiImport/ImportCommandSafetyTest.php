<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('import command redacts an unexpected source failure', function (): void {
    $missing = storage_path('private/ndi/raw/missing-secret.xlsx');

    $this->artisan('ndi:import', ['files' => [$missing]])
        ->expectsOutputToContain('Import failed unexpectedly. Check the application logs.')
        ->doesntExpectOutputToContain($missing)
        ->doesntExpectOutputToContain('UnableToOpenFile')
        ->assertExitCode(1);
});
