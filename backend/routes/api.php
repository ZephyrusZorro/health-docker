<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\ChargeCatalogController;
use App\Http\Controllers\Api\V1\Admin\FacilityController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admission\AdmissionController;
use App\Http\Controllers\Api\V1\Admission\BedController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Billing\InvoiceController;
use App\Http\Controllers\Api\V1\Billing\PaymentController;
use App\Http\Controllers\Api\V1\Clinical\EncounterController;
use App\Http\Controllers\Api\V1\Clinical\ObservationController;
use App\Http\Controllers\Api\V1\Clinical\PrescriptionController;
use App\Http\Controllers\Api\V1\Identity\DepartmentController;
use App\Http\Controllers\Api\V1\Identity\PatientController;
use App\Http\Controllers\Api\V1\Identity\ProviderController;
use App\Http\Controllers\Api\V1\Laboratory\LabOrderController;
use App\Http\Controllers\Api\V1\Laboratory\LabTestController;
use App\Http\Controllers\Api\V1\Pharmacy\DispenseController;
use App\Http\Controllers\Api\V1\Pharmacy\MedicationController;
use App\Http\Controllers\Api\V1\Public\ContentController;
use App\Http\Controllers\Api\V1\Public\DoctorDirectoryController;
use App\Http\Controllers\Api\V1\Public\PublicAppointmentController;
use App\Http\Controllers\Api\V1\Scheduling\AppointmentController;
use App\Http\Controllers\Api\V1\Scheduling\SlotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Nishat HMS — API v1 routes
|--------------------------------------------------------------------------
| Conventions:
|   - All endpoints are JSON; throttled per authenticated user or IP.
|   - RBAC is enforced server-side (SRS §12.1). The permission middleware
|     matches the permission names seeded in RoleAndPermissionSeeder.
|   - Public website endpoints live under /public and use a looser throttle.
|   - Internal staff endpoints require `auth:sanctum` + permission.
*/

Route::prefix('v1')->group(function () {

    // =========================================================================
    // PUBLIC — website & patient-facing (SRS §4.11)
    // No auth. Rate limited against abuse.
    // =========================================================================
    Route::prefix('public')->middleware('throttle:60,1')->group(function () {
        Route::get('doctors',                    [DoctorDirectoryController::class, 'index']);
        Route::get('doctors/{provider}',         [DoctorDirectoryController::class, 'show']);
        Route::get('departments',                [DoctorDirectoryController::class, 'departments']);

        Route::get('availability',               [PublicAppointmentController::class, 'availability']);
        Route::post('appointments',              [PublicAppointmentController::class, 'book'])
             ->middleware('throttle:10,1');  // tighter on booking
        Route::post('appointments/status',       [PublicAppointmentController::class, 'status']);

        Route::get('content/pages',              [ContentController::class, 'pages']);
        Route::get('content/pages/{slug}',       [ContentController::class, 'page']);
        Route::get('content/announcements',      [ContentController::class, 'announcements']);
    });

    // =========================================================================
    // AUTH — login/logout/me/change-password (SRS §12.1)
    // =========================================================================
    Route::prefix('auth')->group(function () {
        Route::post('login',           [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::get('me',               [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });
    });

    // =========================================================================
    // AUTHENTICATED — all internal HMS endpoints
    // =========================================================================
    Route::middleware(['auth:sanctum', 'facility.context', 'audit'])->group(function () {

        // ---------------------------------------------------------------------
        // IDENTITY — patients, providers, departments (SRS §4.1)
        // ---------------------------------------------------------------------
        Route::prefix('patients')->group(function () {
            Route::get('/',                       [PatientController::class, 'index'])
                 ->middleware('permission:patients.view');
            Route::post('/',                      [PatientController::class, 'store'])
                 ->middleware('permission:patients.create');
            Route::get('{patient}',               [PatientController::class, 'show'])
                 ->middleware('permission:patients.view');
            Route::patch('{patient}',             [PatientController::class, 'update'])
                 ->middleware('permission:patients.update');
            Route::delete('{patient}',            [PatientController::class, 'destroy'])
                 ->middleware('permission:patients.delete');

            // Duplicate detection & merge (SRS §4.1 Business Rules)
            Route::get('{patient}/duplicates',    [PatientController::class, 'duplicates'])
                 ->middleware('permission:patients.view');
            Route::post('{patient}/merge',        [PatientController::class, 'merge'])
                 ->middleware('permission:patients.merge');

            // Observation/vitals history for the chart graphing (SRS §4.6)
            Route::get('{patient}/observations',
                 fn (\Illuminate\Http\Request $r, $patient) =>
                    app(ObservationController::class)->indexForPatient($r, (int) $patient)
            )->middleware('permission:emr.view');
        });

        Route::apiResource('providers',    ProviderController::class)
             ->middleware('permission:providers.view')->except(['store', 'destroy']);
        Route::get('providers/{provider}/today',
             [ProviderController::class, 'todayDashboard']
        )->middleware('permission:emr.view');

        Route::apiResource('departments',  DepartmentController::class)
             ->middleware('permission:departments.manage')->except(['show']);

        // ---------------------------------------------------------------------
        // SCHEDULING — appointments, slots, queue (SRS §4.2)
        // ---------------------------------------------------------------------
        Route::prefix('appointments')->middleware('permission:appointments.view')->group(function () {
            Route::get('/',                            [AppointmentController::class, 'index']);
            Route::post('/',                           [AppointmentController::class, 'store'])
                 ->withoutMiddleware('permission:appointments.view')
                 ->middleware('permission:appointments.create');
            Route::get('{appointment}',                [AppointmentController::class, 'show']);

            // Lifecycle transitions (SRS §4.2)
            Route::post('{appointment}/check-in',      [AppointmentController::class, 'checkIn'])
                 ->withoutMiddleware('permission:appointments.view')
                 ->middleware('permission:appointments.checkin');
            Route::post('{appointment}/start-consult', [AppointmentController::class, 'startConsult'])
                 ->withoutMiddleware('permission:appointments.view')
                 ->middleware('permission:appointments.consult');
            Route::post('{appointment}/complete',      [AppointmentController::class, 'complete'])
                 ->withoutMiddleware('permission:appointments.view')
                 ->middleware('permission:appointments.consult');
            Route::post('{appointment}/cancel',        [AppointmentController::class, 'cancel'])
                 ->withoutMiddleware('permission:appointments.view')
                 ->middleware('permission:appointments.cancel');
            Route::post('{appointment}/no-show',       [AppointmentController::class, 'markNoShow'])
                 ->withoutMiddleware('permission:appointments.view')
                 ->middleware('permission:appointments.cancel');
        });

        Route::get('slots',                        [SlotController::class, 'index'])
             ->middleware('permission:appointments.view');

        // ---------------------------------------------------------------------
        // CLINICAL — encounters, observations, prescriptions (SRS §4.3, §4.6)
        // ---------------------------------------------------------------------
        Route::prefix('encounters')->middleware('permission:emr.view')->group(function () {
            Route::get('/',                         [EncounterController::class, 'index']);
            Route::post('/',                        [EncounterController::class, 'store'])
                 ->withoutMiddleware('permission:emr.view')
                 ->middleware('permission:emr.write');
            Route::get('{encounter}',               [EncounterController::class, 'show']);
            Route::patch('{encounter}',             [EncounterController::class, 'update'])
                 ->withoutMiddleware('permission:emr.view')
                 ->middleware('permission:emr.write');
            Route::post('{encounter}/close',        [EncounterController::class, 'close'])
                 ->withoutMiddleware('permission:emr.view')
                 ->middleware('permission:emr.write');

            // Vitals — nurse path (same controller for convenience)
            Route::post('{encounter}/vitals',       [ObservationController::class, 'storeVitals'])
                 ->withoutMiddleware('permission:emr.view')
                 ->middleware('permission:emr.vitals');
            Route::get('{encounter}/observations',  [ObservationController::class, 'indexForEncounter']);
        });

        Route::apiResource('prescriptions', PrescriptionController::class)
             ->middleware('permission:emr.prescribe')
             ->except(['show', 'update'])
             ->parameters(['prescriptions' => 'prescription']);
        Route::get('prescriptions/{prescription}', [PrescriptionController::class, 'show'])
             ->middleware('permission:emr.view');

        // ---------------------------------------------------------------------
        // PHARMACY — catalog, dispense, inventory (SRS §4.7)
        // ---------------------------------------------------------------------
        Route::prefix('pharmacy')->group(function () {
            Route::get('medications',              [MedicationController::class, 'index'])
                 ->middleware('permission:pharmacy.view');
            Route::post('medications',             [MedicationController::class, 'store'])
                 ->middleware('permission:pharmacy.catalog');
            Route::get('medications/{medication}', [MedicationController::class, 'show'])
                 ->middleware('permission:pharmacy.view');
            Route::patch('medications/{medication}',[MedicationController::class, 'update'])
                 ->middleware('permission:pharmacy.catalog');

            Route::get('dispenses',                [DispenseController::class, 'index'])
                 ->middleware('permission:pharmacy.view');
            Route::post('dispenses',               [DispenseController::class, 'store'])
                 ->middleware('permission:pharmacy.dispense');
            Route::get('dispenses/{dispense}',     [DispenseController::class, 'show'])
                 ->middleware('permission:pharmacy.view');
        });

        // ---------------------------------------------------------------------
        // LABORATORY — test catalog, orders, samples, results (SRS §4.8)
        // ---------------------------------------------------------------------
        Route::prefix('lab')->group(function () {
            Route::get('tests',                    [LabTestController::class, 'index'])
                 ->middleware('permission:lab.view');
            Route::post('tests',                   [LabTestController::class, 'store'])
                 ->middleware('permission:lab.catalog');
            Route::get('tests/{labTest}',          [LabTestController::class, 'show'])
                 ->middleware('permission:lab.view');
            Route::patch('tests/{labTest}',        [LabTestController::class, 'update'])
                 ->middleware('permission:lab.catalog');

            Route::get('orders',                   [LabOrderController::class, 'index'])
                 ->middleware('permission:lab.view');
            Route::post('orders',                  [LabOrderController::class, 'store'])
                 ->middleware('permission:lab.order');
            Route::get('orders/{labOrder}',        [LabOrderController::class, 'show'])
                 ->middleware('permission:lab.view');

            Route::post('orders/{labOrder}/collect-sample',
                 [LabOrderController::class, 'collectSample']
            )->middleware('permission:lab.sample');

            Route::post('order-tests/{labOrderTest}/result',
                 [LabOrderController::class, 'storeResult']
            )->middleware('permission:lab.result');
            Route::post('results/{labResult}/verify',
                 [LabOrderController::class, 'verifyResult']
            )->middleware('permission:lab.verify');
        });

        // ---------------------------------------------------------------------
        // ADMISSION & BEDS — IPD (SRS §4.4, §4.10)
        // ---------------------------------------------------------------------
        Route::prefix('admissions')->middleware('permission:ipd.view')->group(function () {
            Route::get('/',                              [AdmissionController::class, 'index']);
            Route::post('/',                             [AdmissionController::class, 'store'])
                 ->withoutMiddleware('permission:ipd.view')
                 ->middleware('permission:ipd.admit');
            Route::get('{admission}',                    [AdmissionController::class, 'show']);
            Route::post('{admission}/transfer-bed',      [AdmissionController::class, 'transferBed'])
                 ->withoutMiddleware('permission:ipd.view')
                 ->middleware('permission:ipd.transfer');
            Route::post('{admission}/discharge',         [AdmissionController::class, 'discharge'])
                 ->withoutMiddleware('permission:ipd.view')
                 ->middleware('permission:ipd.discharge');
        });

        Route::prefix('beds')->middleware('permission:ipd.view')->group(function () {
            Route::get('/',                              [BedController::class, 'index']);
            Route::get('occupancy',                      [BedController::class, 'occupancyDashboard']);
            Route::patch('{bed}/status',                 [BedController::class, 'updateStatus'])
                 ->withoutMiddleware('permission:ipd.view')
                 ->middleware('permission:ipd.bed.manage');
        });

        // ---------------------------------------------------------------------
        // BILLING — invoices, payments (SRS §4.9)
        // ---------------------------------------------------------------------
        Route::prefix('billing')->group(function () {
            Route::get('invoices',                       [InvoiceController::class, 'index'])
                 ->middleware('permission:billing.view');
            Route::get('invoices/{invoice}',             [InvoiceController::class, 'show'])
                 ->middleware('permission:billing.view');
            Route::post('invoices/{invoice}/refresh',    [InvoiceController::class, 'refresh'])
                 ->middleware('permission:billing.edit');
            Route::post('invoices/{invoice}/finalise',   [InvoiceController::class, 'finalise'])
                 ->middleware('permission:billing.finalise');
            Route::post('invoices/{invoice}/discount',   [InvoiceController::class, 'applyDiscount'])
                 ->middleware('permission:billing.discount');

            Route::post('payments',                      [PaymentController::class, 'store'])
                 ->middleware('permission:billing.payment');
        });

        // ---------------------------------------------------------------------
        // ADMIN — users, roles, facility, charges, audit (SRS §4.12)
        // ---------------------------------------------------------------------
        Route::prefix('admin')->middleware('permission:admin.access')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::post('users/{user}/reset-password',   [UserController::class, 'resetPassword']);

            Route::get('roles',                          [RoleController::class, 'index']);
            Route::get('roles/{role}',                   [RoleController::class, 'show']);
            Route::post('roles',                         [RoleController::class, 'store'])
                 ->middleware('permission:admin.roles');
            Route::patch('roles/{role}',                 [RoleController::class, 'update'])
                 ->middleware('permission:admin.roles');
            Route::get('permissions',                    [RoleController::class, 'permissions']);

            Route::apiResource('facilities', FacilityController::class)->except(['destroy']);

            Route::get('charge-catalog',                 [ChargeCatalogController::class, 'index']);
            Route::post('charge-catalog',                [ChargeCatalogController::class, 'store']);
            Route::patch('charge-catalog/{chargeCatalog}',[ChargeCatalogController::class, 'update']);

            Route::get('audit-logs',                     [AuditLogController::class, 'index']);
            Route::get('audit-logs/{auditLog}',          [AuditLogController::class, 'show']);
        });
    });
});
