<?php

namespace Database\Seeders;

use App\Models\Billing\PayerType;
use App\Models\Identity\Facility;
use Illuminate\Database\Seeder;

class PayerTypeSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::firstOrFail();

        $types = [
            ['code' => 'SELF', 'name' => 'Self-pay',                  'type' => 'cash',       'discount_percent' => 0,   'requires_approval' => false],
            ['code' => 'INS',  'name' => 'Insurance',                  'type' => 'insurance',  'discount_percent' => 0,   'requires_approval' => true],
            ['code' => 'CORP', 'name' => 'Corporate',                  'type' => 'corporate',  'discount_percent' => 10,  'requires_approval' => false],
            ['code' => 'GOVT', 'name' => 'Government scheme',          'type' => 'government', 'discount_percent' => 15,  'requires_approval' => true],
            ['code' => 'CHAR', 'name' => 'Charity / free treatment',   'type' => 'charity',    'discount_percent' => 100, 'requires_approval' => true],
        ];
        foreach ($types as $t) {
            PayerType::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $t['code']],
                array_merge($t, ['facility_id' => $facility->id, 'is_active' => true])
            );
        }
    }
}
