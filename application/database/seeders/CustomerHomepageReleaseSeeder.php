<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Narrow, repeatable application path for the accepted customer changes in
 * Issues #45-#47. It deliberately excludes unrelated production seeders.
 */
final class CustomerHomepageReleaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CustomerHomepageCollectionsSeeder::class,
            CustomerHomepageCompositionSeeder::class,
            // Compatibility only: #47's journal owns every hero value that it
            // applied. The older #45 seeder may handle an exact legacy value
            // only when that field is not already present in the journal.
            CustomerNavigationRevisionSeeder::class,
        ]);
    }
}
