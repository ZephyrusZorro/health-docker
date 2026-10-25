<?php

namespace Database\Seeders;

use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RBAC — SRS §3 User Roles + §12.1 Access Control.
 *
 * THIS FILE IS THE AUTHORITATIVE POLICY DOCUMENT. If a permission isn't
 * listed here, it doesn't exist. Middleware in routes/api.php expects the
 * exact permission names defined below.
 *
 * Structure:
 *   1. Define every permission (grouped by category).
 *   2. Define every role (SRS §3 — 10 roles).
 *   3. Grant role → permission mappings.
 */
class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = $this->permissions();

        DB::transaction(function () use ($permissions) {
            // ---- 1. Permissions ----
            foreach ($permissions as $category => $names) {
                foreach ($names as $name) {
                    Permission::updateOrCreate(
                        ['name' => $name, 'guard_name' => 'web'],
                        ['category' => $category]
                    );
                }
            }

            // ---- 2. Roles ----
            $roles = [
                'super_admin'     => ['display' => 'Super Admin',     'desc' => 'Full platform control — all modules, system config, audit logs.'],
                'hospital_admin'  => ['display' => 'Hospital Admin',  'desc' => 'Hospital setup, staff, pricing, master data, reports.'],
                'receptionist'    => ['display' => 'Receptionist',    'desc' => 'Patient registration, appointments, queue, billing initiation.'],
                'doctor'          => ['display' => 'Doctor',          'desc' => 'Consultation, EMR, prescriptions, orders, discharge.'],
                'nurse'           => ['display' => 'Nurse',           'desc' => 'Vitals, nursing notes, medication admin, ward workflow.'],
                'pharmacist'      => ['display' => 'Pharmacist',      'desc' => 'Medicine dispensing, stock, supplier handling, expiry.'],
                'lab_technician'  => ['display' => 'Lab Technician',  'desc' => 'Test processing, sample tracking, result entry.'],
                'cashier'         => ['display' => 'Cashier',         'desc' => 'Invoices, payment collection, refunds (with approval).'],
                'patient'         => ['display' => 'Patient',         'desc' => 'Portal access — own records + bookings (Phase 3).'],
                'management'      => ['display' => 'Management',      'desc' => 'Read-only dashboards, financial & operational reports.'],
            ];

            foreach ($roles as $name => $meta) {
                Role::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['display_name' => $meta['display'], 'description' => $meta['desc']]
                );
            }

            // ---- 3. Mappings ----
            $this->mapSuperAdmin();
            $this->mapHospitalAdmin();
            $this->mapReceptionist();
            $this->mapDoctor();
            $this->mapNurse();
            $this->mapPharmacist();
            $this->mapLabTechnician();
            $this->mapCashier();
            $this->mapManagement();
            $this->mapPatient();
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /** Every permission, grouped by UI category. */
    private function permissions(): array
    {
        return [
            'Patients' => [
                'patients.view', 'patients.create', 'patients.update', 'patients.delete', 'patients.merge',
            ],
            'Providers' => [
                'providers.view', 'providers.manage', 'departments.manage',
            ],
            'Appointments' => [
                'appointments.view', 'appointments.create', 'appointments.checkin',
                'appointments.consult', 'appointments.cancel',
            ],
            'EMR' => [
                'emr.view', 'emr.write', 'emr.vitals', 'emr.prescribe', 'emr.orders',
                'emr.attachments', 'emr.diagnose',
            ],
            'Pharmacy' => [
                'pharmacy.view', 'pharmacy.catalog', 'pharmacy.dispense',
                'pharmacy.stock', 'pharmacy.suppliers', 'pharmacy.po',
            ],
            'Laboratory' => [
                'lab.view', 'lab.catalog', 'lab.order', 'lab.sample', 'lab.result', 'lab.verify',
            ],
            'IPD' => [
                'ipd.view', 'ipd.admit', 'ipd.transfer', 'ipd.discharge',
                'ipd.bed.manage', 'ipd.nursing',
            ],
            'Billing' => [
                'billing.view', 'billing.edit', 'billing.finalise',
                'billing.payment', 'billing.refund', 'billing.discount',
            ],
            'Admin' => [
                'admin.access', 'admin.roles', 'admin.audit', 'admin.settings',
            ],
            'Reports' => [
                'reports.operational', 'reports.financial', 'reports.clinical',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Role mappings (SRS §3)
    // -------------------------------------------------------------------------

    private function mapSuperAdmin(): void
    {
        Role::findByName('super_admin')->syncPermissions(
            Permission::pluck('name')->all()
        );
    }

    private function mapHospitalAdmin(): void
    {
        // Everything except cross-tenant system config (which in this SRS is Super Admin only).
        Role::findByName('hospital_admin')->syncPermissions(
            Permission::whereNotIn('name', ['admin.settings'])->pluck('name')->all()
        );
    }

    private function mapReceptionist(): void
    {
        Role::findByName('receptionist')->syncPermissions([
            'patients.view', 'patients.create', 'patients.update',
            'providers.view',
            'appointments.view', 'appointments.create', 'appointments.checkin', 'appointments.cancel',
            'billing.view', 'billing.payment',  // initiate payment collection
        ]);
    }

    private function mapDoctor(): void
    {
        Role::findByName('doctor')->syncPermissions([
            'patients.view',
            'providers.view',
            'appointments.view', 'appointments.consult',
            'emr.view', 'emr.write', 'emr.vitals', 'emr.prescribe', 'emr.orders',
            'emr.attachments', 'emr.diagnose',
            'pharmacy.view',        // read-only for drug lookup
            'lab.view', 'lab.order',
            'ipd.view', 'ipd.admit', 'ipd.discharge',
            'reports.clinical',
        ]);
    }

    private function mapNurse(): void
    {
        Role::findByName('nurse')->syncPermissions([
            'patients.view',
            'appointments.view', 'appointments.checkin',
            'emr.view', 'emr.vitals', 'emr.attachments',
            'ipd.view', 'ipd.bed.manage', 'ipd.nursing',
            'lab.view',
            'pharmacy.view',
        ]);
    }

    private function mapPharmacist(): void
    {
        Role::findByName('pharmacist')->syncPermissions([
            'patients.view',                    // to dispense to the right person
            'pharmacy.view', 'pharmacy.catalog', 'pharmacy.dispense',
            'pharmacy.stock', 'pharmacy.suppliers', 'pharmacy.po',
            'billing.view',                     // sees what's been invoiced
            'emr.view',                         // read prescriptions
        ]);
    }

    private function mapLabTechnician(): void
    {
        Role::findByName('lab_technician')->syncPermissions([
            'patients.view',
            'lab.view', 'lab.sample', 'lab.result',
            'emr.view',
        ]);
    }

    private function mapCashier(): void
    {
        Role::findByName('cashier')->syncPermissions([
            'patients.view',
            'billing.view', 'billing.edit', 'billing.finalise',
            'billing.payment', 'billing.refund',
            'reports.financial',
        ]);
    }

    private function mapManagement(): void
    {
        // Read-only across the board.
        Role::findByName('management')->syncPermissions([
            'patients.view', 'providers.view',
            'appointments.view',
            'emr.view',
            'pharmacy.view', 'lab.view', 'ipd.view', 'billing.view',
            'reports.operational', 'reports.financial', 'reports.clinical',
        ]);
    }

    private function mapPatient(): void
    {
        // Phase 3: patient portal. Minimal permissions for own data access
        // — enforced row-level in controllers via policies, not via
        // permission alone (handled in the patient portal layer).
        Role::findByName('patient')->syncPermissions([]);
    }
}
