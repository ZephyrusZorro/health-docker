<?php

namespace Database\Seeders;

use App\Models\Identity\Department;
use App\Models\Identity\Facility;
use App\Models\Identity\Provider;
use App\Models\Identity\Staff;
use App\Models\Identity\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates one demo user for every role (SRS §3) — total 10 users.
 * All share password "password" for local dev convenience.
 *
 * These accounts drive the seeded demo data (appointments, prescriptions,
 * dispenses, bed assignments) in DemoDataSeeder.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::firstOrFail();

        // Departments used for provider back-fill
        $genDept   = Department::where('code', 'GEN')->first();
        $cardDept  = Department::where('code', 'CRD')->first();
        $pedsDept  = Department::where('code', 'PED')->first();
        $labDept   = Department::where('code', 'LAB')->first();
        $pharDept  = Department::where('code', 'PHR')->first();
        $admDept   = Department::where('code', 'ADM')->first();

        $users = [
            // Super admin
            [
                'role' => 'super_admin', 'name' => 'System Administrator', 'email' => 'super@nishat.local',
                'department_id' => $admDept?->id,
                'staff' => ['designation' => 'System Administrator'],
            ],
            // Hospital admin
            [
                'role' => 'hospital_admin', 'name' => 'Priya Sharma', 'email' => 'admin@nishat.local',
                'department_id' => $admDept?->id,
                'staff' => ['designation' => 'Hospital Administrator'],
            ],
            // Receptionist
            [
                'role' => 'receptionist', 'name' => 'Anita Reddy', 'email' => 'reception@nishat.local',
                'department_id' => $admDept?->id,
                'staff' => ['designation' => 'Front Desk Receptionist'],
            ],
            // Doctors — three of them
            [
                'role' => 'doctor', 'name' => 'Dr. Arun Kumar', 'email' => 'arun@nishat.local',
                'department_id' => $cardDept?->id,
                'provider' => [
                    'provider_type' => 'doctor',
                    'registration_number' => 'MCI-123456',
                    'qualification' => 'MBBS, MD (Cardiology)',
                    'specialization' => 'Cardiology',
                    'years_of_experience' => 15,
                    'consultation_fee' => 800,
                    'bio' => 'Senior cardiologist with 15 years of experience in interventional cardiology.',
                    'languages' => ['English', 'Hindi', 'Telugu'],
                ],
            ],
            [
                'role' => 'doctor', 'name' => 'Dr. Meera Iyer', 'email' => 'meera@nishat.local',
                'department_id' => $genDept?->id,
                'provider' => [
                    'provider_type' => 'doctor',
                    'registration_number' => 'MCI-234567',
                    'qualification' => 'MBBS, MD (General Medicine)',
                    'specialization' => 'General Medicine',
                    'years_of_experience' => 8,
                    'consultation_fee' => 500,
                    'bio' => 'General physician focused on preventive care and chronic disease management.',
                    'languages' => ['English', 'Hindi', 'Tamil'],
                ],
            ],
            [
                'role' => 'doctor', 'name' => 'Dr. Rohan Gupta', 'email' => 'rohan@nishat.local',
                'department_id' => $pedsDept?->id,
                'provider' => [
                    'provider_type' => 'doctor',
                    'registration_number' => 'MCI-345678',
                    'qualification' => 'MBBS, DCH, MD (Pediatrics)',
                    'specialization' => 'Pediatrics',
                    'years_of_experience' => 10,
                    'consultation_fee' => 600,
                    'bio' => 'Pediatrician specialising in neonatal and adolescent care.',
                    'languages' => ['English', 'Hindi'],
                ],
            ],

            // Nurses
            [
                'role' => 'nurse', 'name' => 'Nurse Lakshmi',  'email' => 'lakshmi@nishat.local',
                'department_id' => $genDept?->id,
                'provider' => ['provider_type' => 'nurse', 'qualification' => 'GNM'],
            ],
            [
                'role' => 'nurse', 'name' => 'Nurse Ravi',  'email' => 'ravi@nishat.local',
                'department_id' => $pedsDept?->id,
                'provider' => ['provider_type' => 'nurse', 'qualification' => 'BSc Nursing'],
            ],

            // Pharmacist
            [
                'role' => 'pharmacist', 'name' => 'Suresh Rao', 'email' => 'pharmacy@nishat.local',
                'department_id' => $pharDept?->id,
                'provider' => ['provider_type' => 'pharmacist', 'qualification' => 'B. Pharm'],
            ],

            // Lab technician
            [
                'role' => 'lab_technician', 'name' => 'Deepa Nair', 'email' => 'lab@nishat.local',
                'department_id' => $labDept?->id,
                'provider' => ['provider_type' => 'lab_technician', 'qualification' => 'DMLT'],
            ],

            // Cashier
            [
                'role' => 'cashier', 'name' => 'Kiran Shetty', 'email' => 'cashier@nishat.local',
                'department_id' => $admDept?->id,
                'staff' => ['designation' => 'Cashier'],
            ],

            // Management
            [
                'role' => 'management', 'name' => 'Sanjay Malhotra', 'email' => 'management@nishat.local',
                'department_id' => $admDept?->id,
                'staff' => ['designation' => 'Operations Head'],
            ],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'facility_id' => $facility->id,
                    'name'        => $u['name'],
                    'password'    => Hash::make('password'),
                    'user_type'   => 'staff',
                    'is_active'   => true,
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles([$u['role']]);

            if (isset($u['provider'])) {
                Provider::updateOrCreate(
                    ['user_id' => $user->id],
                    array_merge($u['provider'], [
                        'user_id'             => $user->id,
                        'facility_id'         => $facility->id,
                        'department_id'       => $u['department_id'] ?? null,
                        'is_available_online' => ($u['provider']['provider_type'] ?? '') === 'doctor',
                    ])
                );
            }

            if (isset($u['staff'])) {
                Staff::updateOrCreate(
                    ['user_id' => $user->id],
                    array_merge($u['staff'], [
                        'user_id'       => $user->id,
                        'facility_id'   => $facility->id,
                        'department_id' => $u['department_id'] ?? null,
                        'date_of_joining' => now()->subYears(rand(1, 6)),
                    ])
                );
            }
        }
    }
}
