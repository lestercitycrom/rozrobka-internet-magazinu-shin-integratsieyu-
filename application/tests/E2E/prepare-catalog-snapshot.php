<?php

use App\Models\User;
use App\Modules\Catalog\Models\CurrentStock;
use App\Modules\Catalog\Models\SkuPrice;
use App\Modules\Catalog\Models\SupplierSku;
use Illuminate\Contracts\Console\Kernel;

// This fixture may only recreate its own local SQLite file, never the application database.
$root = dirname(__DIR__, 2);
$expected = $root.'/database/snapshot-e2e.sqlite';
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || str_replace('\\', '/', (string) getenv('DB_DATABASE')) !== str_replace('\\', '/', $expected)
    || getenv('DB_URL') !== '' || getenv('NDI_CATALOG_MODE') !== 'snapshot') {
    throw new RuntimeException('Refusing snapshot fixture outside its isolated test database.');
}
if (! is_file($expected)) {
    touch($expected);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (config('database.default') !== 'sqlite' || realpath(config('database.connections.sqlite.database')) !== realpath($expected)
    || config('payments.enabled') || config('ndi_api.order_enabled')) {
    throw new RuntimeException('Refusing unsafe resolved snapshot fixture configuration.');
}
foreach ([['migrate:fresh', ['--force' => true]], ['db:seed', ['--class' => 'E2eStorefrontSeeder', '--force' => true]]] as [$command, $arguments]) {
    if ($kernel->call($command, $arguments) !== 0) {
        throw new RuntimeException('Snapshot fixture setup failed.');
    }
}
$ids = SupplierSku::query()
    ->whereIn('external_sku', ['000205550016', 'DEMO-WHEEL-18112'])->pluck('id');
if ($ids->count() !== 2) {
    throw new RuntimeException('Snapshot fixture products missing.');
}
SkuPrice::query()->whereIn('supplier_sku_id', $ids)->update(['observed_at' => '2026-03-01 12:00:00']);
CurrentStock::query()->whereIn('supplier_sku_id', $ids)->update(['observed_at' => '2026-03-01 12:00:00']);
User::factory()->owner()->create(['email' => 'e2e-admin@example.test', 'password' => 'e2e-only-password', 'locale' => 'nb']);
