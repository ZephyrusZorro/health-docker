<?php

namespace Database\Seeders;

use App\Models\Admission\Bed;
use App\Models\Admission\Room;
use App\Models\Admission\Ward;
use App\Models\Identity\Facility;
use Illuminate\Database\Seeder;

/**
 * Builds the bed inventory:
 *   - 5 wards (general, semi-private, private, ICU, emergency)
 *   - ~13 rooms
 *   - ~46 beds, all initially available
 *
 * Rates are realistic for a tier-2 nursing home in India (INR/day).
 */
class WardAndBedSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::firstOrFail();

        $wards = [
            ['code' => 'GEN-W', 'name' => 'General Ward',       'category' => 'general',      'rooms' => 4, 'beds_per_room' => 4, 'daily_rate' => 1000],
            ['code' => 'SPV-W', 'name' => 'Semi-private Wing',  'category' => 'semi_private', 'rooms' => 3, 'beds_per_room' => 2, 'daily_rate' => 2500],
            ['code' => 'PVT-W', 'name' => 'Private Wing',       'category' => 'private',      'rooms' => 4, 'beds_per_room' => 1, 'daily_rate' => 5000],
            ['code' => 'ICU',   'name' => 'Intensive Care Unit','category' => 'icu',          'rooms' => 1, 'beds_per_room' => 6, 'daily_rate' => 12000],
            ['code' => 'EMG-W', 'name' => 'Emergency Ward',     'category' => 'emergency',    'rooms' => 1, 'beds_per_room' => 4, 'daily_rate' => 2000],
        ];

        $totalBeds = 0;

        foreach ($wards as $cfg) {
            $ward = Ward::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $cfg['code']],
                [
                    'facility_id' => $facility->id,
                    'name'        => $cfg['name'],
                    'category'    => $cfg['category'],
                    'building'    => 'Main',
                    'floor'       => 'Ground',
                    'total_beds'  => $cfg['rooms'] * $cfg['beds_per_room'],
                    'is_active'   => true,
                ]
            );

            for ($r = 1; $r <= $cfg['rooms']; $r++) {
                $roomCode = "{$cfg['code']}-R{$r}";
                $room = Room::updateOrCreate(
                    ['ward_id' => $ward->id, 'code' => $roomCode],
                    [
                        'ward_id'    => $ward->id,
                        'name'       => "Room {$r}",
                        'capacity'   => $cfg['beds_per_room'],
                        'daily_rate' => $cfg['daily_rate'],
                        'amenities'  => $cfg['category'] === 'private' ? ['AC', 'TV', 'Attached bathroom']
                                       : ($cfg['category'] === 'icu' ? ['Ventilator', 'Cardiac monitor']
                                       : ['AC']),
                        'is_active'  => true,
                    ]
                );

                for ($b = 1; $b <= $cfg['beds_per_room']; $b++) {
                    $bedNumber = sprintf('%s-%02d-%c', $cfg['code'], $r, ord('A') + ($b - 1));
                    Bed::updateOrCreate(
                        ['room_id' => $room->id, 'bed_number' => $bedNumber],
                        [
                            'facility_id' => $facility->id,
                            'ward_id'     => $ward->id,
                            'room_id'     => $room->id,
                            'bed_number'  => $bedNumber,
                            'status'      => Bed::STATUS_AVAILABLE,
                            'notes'       => null,
                        ]
                    );
                    $totalBeds++;
                }
            }
        }
    }
}
