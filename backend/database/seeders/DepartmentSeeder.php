<?php

namespace Database\Seeders;

use App\Models\Identity\Department;
use App\Models\Identity\Facility;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::where('code', 'NH-MAIN')->firstOrFail();

        $departments = [
            ['code' => 'GEN', 'name' => 'General Medicine',   'icon' => 'stethoscope', 'sort_order' => 1],
            ['code' => 'CRD', 'name' => 'Cardiology',          'icon' => 'heart',       'sort_order' => 2],
            ['code' => 'ORT', 'name' => 'Orthopedics',         'icon' => 'bone',        'sort_order' => 3],
            ['code' => 'PED', 'name' => 'Pediatrics',          'icon' => 'baby',        'sort_order' => 4],
            ['code' => 'GYN', 'name' => 'Gynecology',          'icon' => 'female',      'sort_order' => 5],
            ['code' => 'ENT', 'name' => 'ENT',                 'icon' => 'ear',         'sort_order' => 6],
            ['code' => 'DRM', 'name' => 'Dermatology',         'icon' => 'hand',        'sort_order' => 7],
            ['code' => 'RAD', 'name' => 'Radiology',           'icon' => 'scan',        'sort_order' => 8],
            ['code' => 'LAB', 'name' => 'Laboratory',          'icon' => 'vial',        'sort_order' => 9],
            ['code' => 'PHR', 'name' => 'Pharmacy',            'icon' => 'pill',        'sort_order' => 10],
            ['code' => 'EMR', 'name' => 'Emergency',           'icon' => 'ambulance',   'sort_order' => 11],
            ['code' => 'ADM', 'name' => 'Administration',      'icon' => 'shield',      'sort_order' => 99],
        ];

        foreach ($departments as $d) {
            Department::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $d['code']],
                array_merge($d, ['facility_id' => $facility->id, 'is_active' => true])
            );
        }
    }
}
