<?php

namespace Database\Seeders;

use App\Modules\Content\Models\StorefrontSettings;
use Illuminate\Database\Seeder;

/**
 * Guarded rollback for CustomerNavigationRevisionSeeder.
 *
 * Run explicitly only after a rollback runtime gate:
 * php artisan db:seed --class=Database\\Seeders\\RollbackCustomerNavigationRevisionSeeder
 */
final class RollbackCustomerNavigationRevisionSeeder extends Seeder
{
    /** @var array<string, array{from: string, to: string}> */
    private const CHANGES = [
        'hero_text_nb' => [
            'from' => 'Bestill dekkskift hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.',
            'to' => 'Bestill hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.',
        ],
        'hero_text_ru' => [
            'from' => 'Закажите замену колёс дома, на работе или в мастерской. EasyTire упростит сезонную замену и подтвердит детали до визита.',
            'to' => 'Закажите выезд домой, на работу или приезжайте в мастерскую. EasyTire упростит сезонную замену и подтвердит детали до визита.',
        ],
    ];

    public function run(): void
    {
        $settings = StorefrontSettings::query()->find(1);
        if (! $settings) {
            return;
        }

        $manifest = $settings->customer_homepage_composition_manifest;
        if (is_array($manifest) && ($manifest['release'] ?? null) === CustomerHomepageCompositionSeeder::RELEASE) {
            // #47 is the authoritative field-level journal for the cumulative
            // release. Its rollback has already restored every untouched field
            // and preserved every operator-owned field; do not second-guess it.
            return;
        }

        foreach (self::CHANGES as $field => $change) {
            if ($settings->getAttribute($field) === $change['from']) {
                $settings->setAttribute($field, $change['to']);
            }
        }

        if ($settings->isDirty()) {
            $settings->save();
        }
    }
}
