<?php

namespace Database\Seeders;

use App\Modules\Commerce\Enums\ServiceAddonApplicability;
use App\Modules\Commerce\Enums\ServiceAddonBillingMode;
use App\Modules\Commerce\Models\ServiceAddon;
use Illuminate\Database\Seeder;

class ServiceAddonSeeder extends Seeder
{
    public function run(): void
    {
        ServiceAddon::query()->firstOrCreate(['code' => 'mounting'], [
            'title_nb' => 'Montering',
            'title_ru' => 'Монтаж',
            'applicability' => ServiceAddonApplicability::Tire,
            'billing_mode' => ServiceAddonBillingMode::PerItem,
            'currency' => 'NOK',
            'price_minor' => null,
            'is_active' => false,
            'sort_order' => 10,
        ]);

        ServiceAddon::query()->firstOrCreate(['code' => 'balancing'], [
            'title_nb' => 'Balansering',
            'title_ru' => 'Балансировка',
            'applicability' => ServiceAddonApplicability::All,
            'billing_mode' => ServiceAddonBillingMode::PerItem,
            'currency' => 'NOK',
            'price_minor' => null,
            'is_active' => false,
            'sort_order' => 20,
        ]);
    }
}
