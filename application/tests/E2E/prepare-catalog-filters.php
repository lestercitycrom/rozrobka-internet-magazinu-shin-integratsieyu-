<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SupplierSku;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Destructive setup is permitted only for this one hermetic local SQLite fixture.
$root = dirname(__DIR__, 2);
$expected = $root.'/database/filters-e2e.sqlite';
$isolated = $root.'/tmp/filters';
$normalized = static fn (string $path): string => str_replace('\\', '/', $path);
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || $normalized((string) getenv('DB_DATABASE')) !== $normalized($expected)
    || getenv('DB_URL') !== '' || getenv('NDI_CATALOG_MODE') !== 'snapshot'
    || $normalized((string) getenv('LARAVEL_STORAGE_PATH')) !== $normalized($isolated.'/storage')
    || $normalized((string) getenv('APP_CONFIG_CACHE')) !== $normalized($isolated.'/cache/config.php')
    || is_link($expected) || is_link($isolated) || is_file($isolated.'/cache/config.php')) {
    throw new RuntimeException('Refusing filters fixture outside its uncached isolated test database/storage.');
}
foreach (['cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $directory) {
    $path = $isolated.'/'.$directory;
    if (is_link($path) || (! is_dir($path) && ! mkdir($path, 0770, true))) {
        throw new RuntimeException('Unsafe filters fixture directory.');
    }
}
if (! is_file($expected) && ! touch($expected)) {
    throw new RuntimeException('Cannot initialize isolated filters database.');
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath($expected)
    || realpath(storage_path()) !== realpath($isolated.'/storage')) {
    throw new RuntimeException('Resolved filters fixture configuration is not isolated.');
}
foreach (['payments.enabled', 'ndi_api.inquiry_enabled', 'ndi_api.order_enabled', 'ndi_api.c1_test_mode',
    'ndi_api.sandbox.inquiry_enabled', 'ndi_api.sandbox.order_enabled', 'ndi_api.fulfilment_read_enabled',
    'ndi.media_sync_enabled', 'outbox.notion_enabled', 'outbox.telegram_enabled'] as $gate) {
    if (config($gate)) {
        throw new RuntimeException('External integration gate is enabled in filters fixture.');
    }
}
foreach ([['migrate:fresh', ['--force' => true]], ['db:seed', ['--class' => 'E2eStorefrontSeeder', '--force' => true]]] as [$command, $arguments]) {
    if ($kernel->call($command, $arguments) !== 0) {
        throw new RuntimeException('Filters fixture setup failed.');
    }
}
$tire = SupplierSku::query()->where('external_sku', '000205550016')->firstOrFail();
$wheel = SupplierSku::query()->where('external_sku', 'DEMO-WHEEL-18112')->firstOrFail();
DB::table('tire_specs')->where('product_id', $tire->product_id)->update(['aspect_ratio' => '60.00']);

$clone = static function (SupplierSku $source, string $sku, string $title, string $specTable, array $specChanges): void {
    $product = Product::query()->findOrFail($source->product_id)->replicate();
    $product->title = $title;
    $product->model_name = $title;
    $product->save();
    $newSku = $source->replicate();
    $newSku->external_sku = $sku;
    $newSku->product_id = $product->id;
    $newSku->save();
    $spec = (array) DB::table($specTable)->where('product_id', $source->product_id)->first();
    unset($spec['id']);
    DB::table($specTable)->insert([...$spec, 'product_id' => $product->id, ...$specChanges]);
    foreach (['sku_prices', 'current_stock'] as $table) {
        foreach (DB::table($table)->where('supplier_sku_id', $source->id)->get() as $row) {
            $copy = (array) $row;
            unset($copy['id']);
            DB::table($table)->insert([...$copy, 'supplier_sku_id' => $newSku->id, 'observed_at' => '2026-03-01 12:00:00']);
        }
    }
};
for ($index = 1; $index <= 26; $index++) {
    $clone($tire, sprintf('FILTER77-TIRE-%02d', $index), sprintf('Filter77 Tyre %02d', $index), 'tire_specs', []);
}
foreach (['ZERO' => '0.00', 'NEGATIVE' => '-10.00'] as $suffix => $offset) {
    $clone($wheel, 'FILTER77-WHEEL-'.$suffix, 'Filter77 Wheel '.$suffix, 'wheel_specs', [
        'width_in' => '7.50', 'diameter_in' => '20.00', 'bolt_holes' => 5,
        'pcd_mm' => '120.00', 'center_bore_mm' => '60.10', 'offset_et_mm' => $offset,
    ]);
}
$realIds = SupplierSku::query()->where('supplier_id', $tire->supplier_id)->pluck('id');
foreach (['sku_prices', 'current_stock'] as $table) {
    DB::table($table)->whereIn('supplier_sku_id', $realIds)->update(['observed_at' => '2026-03-01 12:00:00']);
}
echo "Isolated catalog filter fixtures ready.\n";
