<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Guarded reverse-order rollback for the customer changes in Issues #45-#47. */
final class RollbackCustomerHomepageReleaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RollbackCustomerHomepageCompositionSeeder::class,
            RollbackCustomerHomepageCollectionsSeeder::class,
            RollbackCustomerNavigationRevisionSeeder::class,
        ]);
    }
}
