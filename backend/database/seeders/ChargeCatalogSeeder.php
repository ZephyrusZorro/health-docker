<?php

namespace Database\Seeders;

use App\Models\Billing\ChargeCatalog;
use App\Models\Identity\Facility;
use Illuminate\Database\Seeder;

class ChargeCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::firstOrFail();

        $items = [
            // Consultations
            ['code' => 'CONS-GEN', 'name' => 'General Consultation',     'category' => 'consultation', 'unit_price' => 500],
            ['code' => 'CONS-SPC', 'name' => 'Specialist Consultation',   'category' => 'consultation', 'unit_price' => 800],
            ['code' => 'CONS-FUP', 'name' => 'Follow-up Consultation',    'category' => 'consultation', 'unit_price' => 300],
            ['code' => 'CONS-EMG', 'name' => 'Emergency Consultation',    'category' => 'emergency',    'unit_price' => 1500],
            ['code' => 'CONS-TEL', 'name' => 'Telemedicine Consultation', 'category' => 'consultation', 'unit_price' => 400],

            // Procedures
            ['code' => 'PROC-DSG', 'name' => 'Dressing / minor',          'category' => 'procedure', 'unit_price' => 200],
            ['code' => 'PROC-INJ', 'name' => 'Injection administration',  'category' => 'procedure', 'unit_price' => 50],
            ['code' => 'PROC-SUT', 'name' => 'Suture removal',            'category' => 'procedure', 'unit_price' => 150],
            ['code' => 'PROC-NEB', 'name' => 'Nebulisation',              'category' => 'procedure', 'unit_price' => 100],

            // Registration / admin
            ['code' => 'ADM-REG',  'name' => 'Registration fee (new)',    'category' => 'registration', 'unit_price' => 100],
            ['code' => 'ADM-FILE', 'name' => 'Medical file handling',     'category' => 'other',        'unit_price' => 50],

            // Bed / room charges
            ['code' => 'BED-GEN', 'name' => 'General ward (per day)',      'category' => 'room_charge', 'unit_price' => 1000],
            ['code' => 'BED-SPV', 'name' => 'Semi-private room (per day)', 'category' => 'room_charge', 'unit_price' => 2500],
            ['code' => 'BED-PVT', 'name' => 'Private room (per day)',      'category' => 'room_charge', 'unit_price' => 5000],
            ['code' => 'BED-ICU', 'name' => 'ICU (per day)',               'category' => 'room_charge', 'unit_price' => 12000],

            // Nursing
            ['code' => 'NUR-IPD', 'name' => 'Nursing care (per day)',      'category' => 'nursing_charge', 'unit_price' => 300],
        ];

        foreach ($items as $i) {
            ChargeCatalog::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $i['code']],
                array_merge($i, [
                    'facility_id' => $facility->id,
                    'tax_percent' => 0,
                    'is_active'   => true,
                ])
            );
        }
    }
}
