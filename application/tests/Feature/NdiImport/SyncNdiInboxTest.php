<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('stable inbox files import once while an unchanged artifact is safely skipped', function (): void {
    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');
    config()->set('ndi.catalog_currency', 'NOK');
    config()->set('ndi.parser_version', 'inbox-test-1.0');
    config()->set('ndi.inbox_stable_seconds', 30);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $file = $inbox.DIRECTORY_SEPARATOR.'stock.xlsx';
    copy(ndiFixture('stock_all_warehouses.xlsx'), $file);
    touch($file, time() - 60);
    config()->set('ndi.inbox_path', $inbox);

    try {
        $this->artisan('ndi:inbox:sync')
            ->expectsOutputToContain('1 imported, 0 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('import_runs')->count())->toBe(1)
            ->and(DB::table('import_runs')->value('run_type'))->toBe('partial_feed')
            ->and(DB::table('current_stock')->count())->toBeGreaterThan(0);

        $this->artisan('ndi:inbox:sync')
            ->expectsOutputToContain('0 imported, 1 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('import_runs')->count())->toBe(1);

        $firstObservedAt = DB::table('current_stock')->max('observed_at');
        $previousMtime = filemtime($file);
        $this->travel(4)->hours();
        touch($file, $previousMtime + 1);

        $this->artisan('ndi:inbox:sync')
            ->expectsOutputToContain('1 imported, 0 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('import_runs')->count())->toBe(2)
            ->and(DB::table('ndi_inbox_receipts')->count())->toBe(2)
            ->and(DB::table('current_stock')->max('observed_at'))->not->toBe($firstObservedAt);
    } finally {
        @unlink($file);
        @rmdir($inbox);
    }
});

test('missing inbox is an explicit failure and an unconfigured inbox is a safe no-op', function (): void {
    config()->set('ndi.inbox_path', null);
    $this->artisan('ndi:inbox:sync')->assertSuccessful();

    config()->set('ndi.inbox_path', sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-easytire-inbox');
    $this->artisan('ndi:inbox:sync')->assertFailed();
});

test('inbox ignores symbolic links instead of importing files outside its root', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symlinks requires an optional Windows privilege.');
    }

    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');
    config()->set('ndi.inbox_stable_seconds', 30);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $link = $inbox.DIRECTORY_SEPARATOR.'outside.xlsx';
    symlink(ndiFixture('stock_all_warehouses.xlsx'), $link);
    config()->set('ndi.inbox_path', $inbox);

    try {
        $this->artisan('ndi:inbox:sync')
            ->expectsOutputToContain('0 imported, 0 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('import_runs')->count())->toBe(0);
    } finally {
        @unlink($link);
        @rmdir($inbox);
    }
});

test('a transient importer lock is retried after backoff instead of losing the receipt', function (): void {
    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');
    config()->set('ndi.inbox_stable_seconds', 30);
    config()->set('ndi.inbox_retry_backoff_minutes', 10);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $file = $inbox.DIRECTORY_SEPARATOR.'stock.xlsx';
    copy(ndiFixture('stock_all_warehouses.xlsx'), $file);
    touch($file, time() - 60);
    config()->set('ndi.inbox_path', $inbox);
    $lock = Cache::lock('ndi-import:'.config('ndi.supplier_code'), 300);

    try {
        expect($lock->get())->toBeTrue();
        $this->artisan('ndi:inbox:sync')->assertFailed();
        expect(DB::table('ndi_inbox_receipts')->value('status'))->toBe('retryable');
        $lock->release();

        $this->travel(11)->minutes();
        $this->artisan('ndi:inbox:sync')->assertSuccessful();
        expect(DB::table('ndi_inbox_receipts')->value('status'))->toBe('succeeded')
            ->and(DB::table('current_stock')->count())->toBeGreaterThan(0);
    } finally {
        $lock->release();
        @unlink($file);
        @rmdir($inbox);
    }
});

test('a terminal invalid file cannot starve a later valid stock snapshot', function (): void {
    Storage::fake('local');
    config()->set('ndi.raw_disk', 'local');
    config()->set('ndi.catalog_currency', 'NOK');
    config()->set('ndi.inbox_stable_seconds', 30);
    $inbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'easytire-ndi-inbox-'.bin2hex(random_bytes(5));
    mkdir($inbox, 0700, true);
    $invalid = $inbox.DIRECTORY_SEPARATOR.'01-invalid.xlsx';
    $valid = $inbox.DIRECTORY_SEPARATOR.'02-stock.xlsx';
    copy(ndiFixture('invalid_decimal.xlsx'), $invalid);
    copy(ndiFixture('stock_all_warehouses.xlsx'), $valid);
    touch($invalid, time() - 120);
    touch($valid, time() - 60);
    config()->set('ndi.inbox_path', $inbox);

    try {
        $this->artisan('ndi:inbox:sync --limit=1')->assertFailed();
        expect(DB::table('ndi_inbox_receipts')->where('status', 'failed')->count())->toBe(1);

        $this->artisan('ndi:inbox:sync --limit=1')
            ->expectsOutputToContain('1 imported, 1 unchanged, 0 failed')
            ->assertSuccessful();
        expect(DB::table('current_stock')->count())->toBeGreaterThan(0);
    } finally {
        @unlink($invalid);
        @unlink($valid);
        @rmdir($inbox);
    }
});
