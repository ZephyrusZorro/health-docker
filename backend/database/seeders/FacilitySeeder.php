<?php

namespace Database\Seeders;

use App\Models\Identity\Facility;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        Facility::updateOrCreate(
            ['code' => 'NH-MAIN'],
            [
                'name'             => 'Nishat Nursing Home',
                'legal_name'       => 'Nishat Nursing Home Pvt. Ltd.',
                'phone'            => '+91 98000 00000',
                'email'            => 'info@nishatnursinghome.com',
                'address'          => [
                    'line1'   => '123 Main Road',
                    'city'    => 'Hyderabad',
                    'state'   => 'Telangana',
                    'pincode' => '500001',
                    'country' => 'India',
                ],
                'timezone'         => 'Asia/Kolkata',
                'default_currency' => 'INR',
                'is_active'        => true,
            ]
        );
    }
}
