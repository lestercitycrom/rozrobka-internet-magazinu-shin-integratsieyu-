<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Production seeding never creates a default user or password.
        $this->call([
            ContentPageSeeder::class,
            HomepageContentSeeder::class,
            PublicServicePriceSeeder::class,
            ServiceAddonSeeder::class,
            SeoServicePageSeeder::class,
            CustomerWordingCorrectionSeeder::class,
        ]);
    }
}
