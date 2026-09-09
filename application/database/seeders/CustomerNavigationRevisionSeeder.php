<?php

namespace Database\Seeders;

use App\Modules\Content\Models\StorefrontSettings;
use Illuminate\Database\Seeder;

/**
 * Guarded content manifest for the 2026-09-09 customer navigation revision.
 *
 * Run explicitly only after a runtime gate:
 * php artisan db:seed --class=Database\\Seeders\\CustomerNavigationRevisionSeeder
 */
final class CustomerNavigationRevisionSeeder extends Seeder
{
    /** @var array<string, array{from: string, to: string}> */
    private const CHANGES = [
        'hero_text_nb' => [
            'from' => 'Bestill hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.',
            'to' => 'Bestill dekkskift hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.',
        ],
        'hero_text_ru' => [
            'from' => 'Закажите выезд домой, на работу или приезжайте в мастерскую. EasyTire упростит сезонную замену и подтвердит детали до визита.',
            'to' => 'Закажите замену колёс дома, на работе или в мастерской. EasyTire упростит сезонную замену и подтвердит детали до визита.',
        ],
    ];

    public function run(): void
    {
        $settings = StorefrontSettings::query()->find(1);
        if (! $settings) {
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
