<?php

namespace Database\Seeders;

use App\Models\Identity\Facility;
use App\Models\Laboratory\LabTest;
use Illuminate\Database\Seeder;

/**
 * Lab test catalog with reference ranges (SRS §4.8). Reference ranges are
 * what drive automatic abnormal-result flagging in LabOrderController.
 */
class LabTestSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::firstOrFail();

        $tests = [
            // Haematology
            ['code' => 'LAB-CBC',   'name' => 'Complete Blood Count (CBC)', 'category' => 'Haematology', 'specimen_type' => 'Blood', 'price' => 400, 'turnaround_hours' => 4, 'test_type' => 'panel'],
            ['code' => 'LAB-HB',    'name' => 'Haemoglobin',                'category' => 'Haematology', 'specimen_type' => 'Blood', 'default_unit' => 'g/dL', 'reference_low' => 12,    'reference_high' => 17,    'price' => 150],
            ['code' => 'LAB-WBC',   'name' => 'WBC Count',                  'category' => 'Haematology', 'specimen_type' => 'Blood', 'default_unit' => '/µL',  'reference_low' => 4000,  'reference_high' => 11000, 'price' => 150],
            ['code' => 'LAB-PLT',   'name' => 'Platelet Count',             'category' => 'Haematology', 'specimen_type' => 'Blood', 'default_unit' => '/µL',  'reference_low' => 150000,'reference_high' => 450000,'price' => 150],
            ['code' => 'LAB-ESR',   'name' => 'ESR',                        'category' => 'Haematology', 'specimen_type' => 'Blood', 'default_unit' => 'mm/hr','reference_low' => 0,    'reference_high' => 20,    'price' => 100],

            // Biochem
            ['code' => 'LAB-FBS',   'name' => 'Fasting Blood Sugar',         'category' => 'Biochemistry', 'specimen_type' => 'Blood',  'default_unit' => 'mg/dL', 'reference_low' => 70,    'reference_high' => 100,  'price' => 120],
            ['code' => 'LAB-PPBS',  'name' => 'Post-prandial Blood Sugar',   'category' => 'Biochemistry', 'specimen_type' => 'Blood',  'default_unit' => 'mg/dL', 'reference_low' => 70,    'reference_high' => 140,  'price' => 120],
            ['code' => 'LAB-HBA1C', 'name' => 'HbA1c',                       'category' => 'Biochemistry', 'specimen_type' => 'Blood',  'default_unit' => '%',     'reference_low' => 4,     'reference_high' => 5.7,  'price' => 500],
            ['code' => 'LAB-LFT',   'name' => 'Liver Function Test',         'category' => 'Biochemistry', 'specimen_type' => 'Blood',  'price' => 600, 'test_type' => 'panel'],
            ['code' => 'LAB-KFT',   'name' => 'Kidney Function Test',        'category' => 'Biochemistry', 'specimen_type' => 'Blood',  'price' => 600, 'test_type' => 'panel'],
            ['code' => 'LAB-SCR',   'name' => 'Serum Creatinine',            'category' => 'Biochemistry', 'specimen_type' => 'Blood',  'default_unit' => 'mg/dL', 'reference_low' => 0.6, 'reference_high' => 1.3, 'price' => 200],
            ['code' => 'LAB-UREA',  'name' => 'Blood Urea',                  'category' => 'Biochemistry', 'specimen_type' => 'Blood',  'default_unit' => 'mg/dL', 'reference_low' => 15,  'reference_high' => 45,  'price' => 200],
            ['code' => 'LAB-CHL',   'name' => 'Total Cholesterol',           'category' => 'Biochemistry', 'specimen_type' => 'Blood',  'default_unit' => 'mg/dL', 'reference_low' => 0,   'reference_high' => 200, 'price' => 250],
            ['code' => 'LAB-LIPID', 'name' => 'Lipid Profile',               'category' => 'Biochemistry', 'specimen_type' => 'Blood',  'price' => 700, 'test_type' => 'panel'],
            ['code' => 'LAB-TSH',   'name' => 'TSH (Thyroid)',               'category' => 'Endocrinology','specimen_type' => 'Blood',  'default_unit' => 'µIU/mL','reference_low' => 0.4, 'reference_high' => 4.5, 'price' => 350],
            ['code' => 'LAB-T3',    'name' => 'Free T3',                     'category' => 'Endocrinology','specimen_type' => 'Blood',  'default_unit' => 'pg/mL','reference_low' => 2.3, 'reference_high' => 4.2, 'price' => 350],
            ['code' => 'LAB-T4',    'name' => 'Free T4',                     'category' => 'Endocrinology','specimen_type' => 'Blood',  'default_unit' => 'ng/dL','reference_low' => 0.8, 'reference_high' => 1.8, 'price' => 350],

            // Urinalysis
            ['code' => 'LAB-URN',   'name' => 'Urine Routine',               'category' => 'Urinalysis',   'specimen_type' => 'Urine', 'price' => 200],

            // Serology
            ['code' => 'LAB-HIV',   'name' => 'HIV (ELISA)',                 'category' => 'Serology',    'specimen_type' => 'Blood', 'price' => 500, 'reference_text' => 'Non-reactive'],
            ['code' => 'LAB-HBSAG', 'name' => 'HBsAg',                       'category' => 'Serology',    'specimen_type' => 'Blood', 'price' => 400, 'reference_text' => 'Non-reactive'],
            ['code' => 'LAB-DENG',  'name' => 'Dengue NS1 Antigen',          'category' => 'Serology',    'specimen_type' => 'Blood', 'price' => 800, 'reference_text' => 'Negative'],
            ['code' => 'LAB-COVID', 'name' => 'COVID-19 RT-PCR',             'category' => 'Molecular',   'specimen_type' => 'Swab',  'price' => 800, 'reference_text' => 'Negative'],

            // Cardiac
            ['code' => 'LAB-TROP',  'name' => 'Troponin-I',                  'category' => 'Cardiac',     'specimen_type' => 'Blood', 'default_unit' => 'ng/mL', 'reference_low' => 0, 'reference_high' => 0.04, 'price' => 800],

            // Imaging (radiology) — uploaded as PDF/DICOM
            ['code' => 'IMG-XCH',   'name' => 'X-ray Chest (PA)',           'category' => 'Radiology',   'specimen_type' => '-', 'price' => 400, 'test_type' => 'radiology'],
            ['code' => 'IMG-USG',   'name' => 'Ultrasound (abdomen)',        'category' => 'Radiology',   'specimen_type' => '-', 'price' => 1500, 'test_type' => 'radiology'],
            ['code' => 'IMG-ECG',   'name' => 'ECG (12-lead)',               'category' => 'Cardiac',     'specimen_type' => '-', 'price' => 300, 'test_type' => 'radiology'],
        ];

        foreach ($tests as $t) {
            LabTest::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $t['code']],
                array_merge([
                    'facility_id'      => $facility->id,
                    'reference_low'    => null,
                    'reference_high'   => null,
                    'reference_text'   => null,
                    'turnaround_hours' => 24,
                    'test_type'        => 'single',
                    'is_active'        => true,
                ], $t)
            );
        }
    }
}
