<?php

use App\Modules\Content\Models\StorefrontSettings;
use Database\Seeders\CustomerNavigationRevisionSeeder;
use Database\Seeders\RollbackCustomerNavigationRevisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer navigation content manifest updates only the exact previous hero copy', function (): void {
    $settings = StorefrontSettings::query()->create([
        'id' => 1,
        'hero_text_nb' => 'Bestill hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.',
        'hero_text_ru' => 'Закажите выезд домой, на работу или приезжайте в мастерскую. EasyTire упростит сезонную замену и подтвердит детали до визита.',
    ]);

    $this->seed(CustomerNavigationRevisionSeeder::class);
    $this->seed(CustomerNavigationRevisionSeeder::class);

    expect($settings->fresh())
        ->hero_text_nb->toBe('Bestill dekkskift hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.')
        ->hero_text_ru->toBe('Закажите замену колёс дома, на работе или в мастерской. EasyTire упростит сезонную замену и подтвердит детали до визита.');
});

test('customer navigation content manifest preserves later operator edits', function (): void {
    $settings = StorefrontSettings::query()->create([
        'id' => 1,
        'hero_text_nb' => 'Redaktørens nyere tekst skal bevares.',
        'hero_text_ru' => 'Более новая редакционная версия должна сохраниться.',
    ]);

    $this->seed(CustomerNavigationRevisionSeeder::class);

    expect($settings->fresh())
        ->hero_text_nb->toBe('Redaktørens nyere tekst skal bevares.')
        ->hero_text_ru->toBe('Более новая редакционная версия должна сохраниться.');
});

test('guarded rollback restores only unchanged manifest values', function (): void {
    $settings = StorefrontSettings::query()->create([
        'id' => 1,
        'hero_text_nb' => 'Bestill hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.',
        'hero_text_ru' => 'Закажите выезд домой, на работу или приезжайте в мастерскую. EasyTire упростит сезонную замену и подтвердит детали до визита.',
    ]);

    $this->seed(CustomerNavigationRevisionSeeder::class);
    $settings->fresh()->update(['hero_text_ru' => 'Новая редакционная версия после выкладки.']);
    $this->seed(RollbackCustomerNavigationRevisionSeeder::class);
    $this->seed(RollbackCustomerNavigationRevisionSeeder::class);

    expect($settings->fresh())
        ->hero_text_nb->toBe('Bestill hjemme, på jobb eller på verksted. EasyTire gjør sesongskiftet enkelt, med tydelig oppfølging før avtalen bekreftes.')
        ->hero_text_ru->toBe('Новая редакционная версия после выкладки.');
});
