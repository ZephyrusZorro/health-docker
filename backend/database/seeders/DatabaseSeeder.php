<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Master seeder. Run `php artisan db:seed` for the full baseline,
 * or `php artisan db:seed --class=DemoDataSeeder` for a realistic
 * demo dataset on top of the baseline.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FacilitySeeder::class,
            DepartmentSeeder::class,
            RoleAndPermissionSeeder::class,
            VisitTypeSeeder::class,
            PayerTypeSeeder::class,
            ChargeCatalogSeeder::class,
            MedicationSeeder::class,
            LabTestSeeder::class,
            WardAndBedSeeder::class,
            NotificationTemplateSeeder::class,
            ContentSeeder::class,

            // Demo data — users, patients, appointments, etc.
            DemoUserSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
