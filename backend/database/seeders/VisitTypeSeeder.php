<?php

namespace Database\Seeders;

use App\Models\Identity\Facility;
use App\Models\Scheduling\VisitType;
use Illuminate\Database\Seeder;

class VisitTypeSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::firstOrFail();

        $types = [
            ['code' => 'NEW',  'name' => 'New Consultation', 'default_duration_minutes' => 20, 'color' => '#3b82f6', 'default_fee' => 500],
            ['code' => 'FUP',  'name' => 'Follow-up',        'default_duration_minutes' => 10, 'color' => '#10b981', 'default_fee' => 300],
            ['code' => 'EMG',  'name' => 'Emergency',        'default_duration_minutes' => 30, 'color' => '#ef4444', 'default_fee' => 800],
            ['code' => 'PROC', 'name' => 'Procedure',        'default_duration_minutes' => 45, 'color' => '#8b5cf6', 'default_fee' => 1500],
            ['code' => 'TEL',  'name' => 'Telemedicine',     'default_duration_minutes' => 15, 'color' => '#06b6d4', 'default_fee' => 400],
            ['code' => 'VAC',  'name' => 'Vaccination',      'default_duration_minutes' => 10, 'color' => '#f59e0b', 'default_fee' => 200],
        ];
        foreach ($types as $t) {
            VisitType::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $t['code']],
                array_merge($t, [
                    'facility_id' => $facility->id,
                    'allow_online_booking' => true,
                    'is_active' => true,
                ])
            );
        }
    }
}
