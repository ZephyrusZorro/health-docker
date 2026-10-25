<?php

namespace Database\Seeders;

use App\Models\Clinical\Medication;
use Illuminate\Database\Seeder;

/**
 * Seeds the Medication (drug catalog) table with ~40 of the most commonly
 * dispensed medicines in Indian clinical practice. This is the catalog —
 * actual stock lives in medicine_batches (seeded by DemoDataSeeder).
 */
class MedicationSeeder extends Seeder
{
    public function run(): void
    {
        $meds = [
            // Analgesics / antipyretics
            ['code' => 'MED-001', 'generic_name' => 'Paracetamol',           'brand_name' => 'Crocin',       'strength' => '500 mg', 'form' => 'tablet',   'category' => 'Analgesic'],
            ['code' => 'MED-002', 'generic_name' => 'Ibuprofen',             'brand_name' => 'Brufen',       'strength' => '400 mg', 'form' => 'tablet',   'category' => 'NSAID'],
            ['code' => 'MED-003', 'generic_name' => 'Diclofenac',            'brand_name' => 'Voveran',      'strength' => '50 mg',  'form' => 'tablet',   'category' => 'NSAID'],
            ['code' => 'MED-004', 'generic_name' => 'Aspirin',               'brand_name' => 'Ecosprin',     'strength' => '75 mg',  'form' => 'tablet',   'category' => 'Antiplatelet'],

            // Antibiotics
            ['code' => 'MED-010', 'generic_name' => 'Amoxicillin',           'brand_name' => 'Mox',          'strength' => '500 mg', 'form' => 'capsule',  'category' => 'Antibiotic', 'is_prescription_required' => true],
            ['code' => 'MED-011', 'generic_name' => 'Azithromycin',          'brand_name' => 'Azee',         'strength' => '500 mg', 'form' => 'tablet',   'category' => 'Antibiotic', 'is_prescription_required' => true],
            ['code' => 'MED-012', 'generic_name' => 'Ciprofloxacin',         'brand_name' => 'Cifran',       'strength' => '500 mg', 'form' => 'tablet',   'category' => 'Antibiotic', 'is_prescription_required' => true],
            ['code' => 'MED-013', 'generic_name' => 'Cefixime',              'brand_name' => 'Taxim-O',      'strength' => '200 mg', 'form' => 'tablet',   'category' => 'Antibiotic', 'is_prescription_required' => true],
            ['code' => 'MED-014', 'generic_name' => 'Doxycycline',           'brand_name' => 'Doxy',         'strength' => '100 mg', 'form' => 'capsule',  'category' => 'Antibiotic', 'is_prescription_required' => true],

            // GI
            ['code' => 'MED-020', 'generic_name' => 'Pantoprazole',          'brand_name' => 'Pan',          'strength' => '40 mg',  'form' => 'tablet',   'category' => 'PPI'],
            ['code' => 'MED-021', 'generic_name' => 'Omeprazole',            'brand_name' => 'Omez',         'strength' => '20 mg',  'form' => 'capsule',  'category' => 'PPI'],
            ['code' => 'MED-022', 'generic_name' => 'Domperidone',           'brand_name' => 'Domstal',      'strength' => '10 mg',  'form' => 'tablet',   'category' => 'Antiemetic'],
            ['code' => 'MED-023', 'generic_name' => 'Ondansetron',           'brand_name' => 'Emeset',       'strength' => '4 mg',   'form' => 'tablet',   'category' => 'Antiemetic'],

            // Cough / cold
            ['code' => 'MED-030', 'generic_name' => 'Cetirizine',            'brand_name' => 'Cetzine',      'strength' => '10 mg',  'form' => 'tablet',   'category' => 'Antihistamine'],
            ['code' => 'MED-031', 'generic_name' => 'Levocetirizine',        'brand_name' => 'Levocet',      'strength' => '5 mg',   'form' => 'tablet',   'category' => 'Antihistamine'],
            ['code' => 'MED-032', 'generic_name' => 'Dextromethorphan',      'brand_name' => 'Benadryl',     'strength' => '10 mg/5ml','form' => 'syrup',  'category' => 'Cough suppressant'],

            // Diabetes
            ['code' => 'MED-040', 'generic_name' => 'Metformin',             'brand_name' => 'Glycomet',     'strength' => '500 mg', 'form' => 'tablet',   'category' => 'Antidiabetic', 'is_prescription_required' => true],
            ['code' => 'MED-041', 'generic_name' => 'Glimepiride',           'brand_name' => 'Amaryl',       'strength' => '2 mg',   'form' => 'tablet',   'category' => 'Antidiabetic', 'is_prescription_required' => true],
            ['code' => 'MED-042', 'generic_name' => 'Insulin (regular)',     'brand_name' => 'Humulin R',    'strength' => '100 IU/ml','form' => 'injection','category' => 'Antidiabetic', 'is_prescription_required' => true, 'storage_instructions' => 'Refrigerate 2-8°C'],

            // Cardiac
            ['code' => 'MED-050', 'generic_name' => 'Amlodipine',            'brand_name' => 'Amlodac',      'strength' => '5 mg',   'form' => 'tablet',   'category' => 'Antihypertensive', 'is_prescription_required' => true],
            ['code' => 'MED-051', 'generic_name' => 'Atenolol',              'brand_name' => 'Aten',         'strength' => '50 mg',  'form' => 'tablet',   'category' => 'Beta blocker', 'is_prescription_required' => true],
            ['code' => 'MED-052', 'generic_name' => 'Atorvastatin',          'brand_name' => 'Atorva',       'strength' => '10 mg',  'form' => 'tablet',   'category' => 'Statin', 'is_prescription_required' => true],
            ['code' => 'MED-053', 'generic_name' => 'Telmisartan',           'brand_name' => 'Telma',        'strength' => '40 mg',  'form' => 'tablet',   'category' => 'Antihypertensive', 'is_prescription_required' => true],

            // Respiratory
            ['code' => 'MED-060', 'generic_name' => 'Salbutamol',            'brand_name' => 'Asthalin',     'strength' => '100 mcg','form' => 'inhaler',  'category' => 'Bronchodilator'],
            ['code' => 'MED-061', 'generic_name' => 'Budesonide',            'brand_name' => 'Budecort',     'strength' => '200 mcg','form' => 'inhaler',  'category' => 'Steroid', 'is_prescription_required' => true],

            // Injections / IV fluids
            ['code' => 'MED-070', 'generic_name' => 'Normal Saline',         'brand_name' => 'NS',           'strength' => '0.9%',   'form' => 'iv_fluid', 'category' => 'IV Fluid'],
            ['code' => 'MED-071', 'generic_name' => 'Ringer Lactate',        'brand_name' => 'RL',           'strength' => '500 ml', 'form' => 'iv_fluid', 'category' => 'IV Fluid'],
            ['code' => 'MED-072', 'generic_name' => 'Dextrose',              'brand_name' => 'D5W',          'strength' => '5%',     'form' => 'iv_fluid', 'category' => 'IV Fluid'],

            // Misc
            ['code' => 'MED-080', 'generic_name' => 'Oral Rehydration Salts','brand_name' => 'Electral',     'strength' => '21 g',   'form' => 'powder',   'category' => 'Electrolyte'],
            ['code' => 'MED-081', 'generic_name' => 'Multivitamin',          'brand_name' => 'Revital',      'strength' => '1 tab',  'form' => 'capsule',  'category' => 'Supplement'],
            ['code' => 'MED-082', 'generic_name' => 'Folic Acid',            'brand_name' => 'Folvite',      'strength' => '5 mg',   'form' => 'tablet',   'category' => 'Supplement'],
            ['code' => 'MED-083', 'generic_name' => 'Iron (ferrous)',        'brand_name' => 'Fefol',        'strength' => '150 mg', 'form' => 'capsule',  'category' => 'Supplement'],

            // Sedatives (controlled)
            ['code' => 'MED-090', 'generic_name' => 'Diazepam',              'brand_name' => 'Calmpose',     'strength' => '5 mg',   'form' => 'tablet',   'category' => 'Sedative',    'is_prescription_required' => true, 'is_controlled' => true],
            ['code' => 'MED-091', 'generic_name' => 'Alprazolam',            'brand_name' => 'Alprax',       'strength' => '0.5 mg', 'form' => 'tablet',   'category' => 'Anxiolytic',  'is_prescription_required' => true, 'is_controlled' => true],

            // Topical
            ['code' => 'MED-100', 'generic_name' => 'Povidone iodine',       'brand_name' => 'Betadine',     'strength' => '5%',     'form' => 'solution', 'category' => 'Antiseptic'],
            ['code' => 'MED-101', 'generic_name' => 'Hydrocortisone',        'brand_name' => 'Efcorlin',     'strength' => '1%',     'form' => 'cream',    'category' => 'Steroid'],

            // Vaccines
            ['code' => 'MED-110', 'generic_name' => 'Tetanus Toxoid',        'brand_name' => 'TT',           'strength' => '0.5 ml', 'form' => 'injection','category' => 'Vaccine', 'storage_instructions' => 'Refrigerate 2-8°C'],
        ];

        foreach ($meds as $m) {
            Medication::updateOrCreate(
                ['code' => $m['code']],
                array_merge([
                    'manufacturer'             => null,
                    'hsn_code'                 => null,
                    'default_tax_percent'      => 12,
                    'is_prescription_required' => false,
                    'is_controlled'            => false,
                    'reorder_level'            => 20,
                    'storage_instructions'     => null,
                    'common_side_effects'      => null,
                    'contraindications'        => [],
                    'is_active'                => true,
                ], $m)
            );
        }
    }
}
