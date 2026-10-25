# API Reference

All endpoints are prefixed with `/api/v1`. Authentication uses Bearer tokens from Laravel Sanctum personal access tokens (obtained via `POST /auth/login`).

Unless noted, responses follow the envelope:

```json
{ "data": { ... } }     // single resource
{ "data": [...], "meta": { "current_page": 1, ... } }    // paginated
```

Errors use standard HTTP status codes with a JSON body:

```json
{ "message": "Human-readable error.", "errors": { "field": ["reason"] } }
```

## Public endpoints (no auth)

| Verb | Path | Purpose |
|------|------|---------|
| GET  | `/public/doctors` | List doctors for the public directory |
| GET  | `/public/departments` | Departments with active providers |
| GET  | `/public/availability?provider_id=&date=` | Available slots for a doctor on a date |
| POST | `/public/appointments` | Book an appointment without an account |
| POST | `/public/appointments/status` | Look up status by appointment # + phone |
| GET  | `/public/content/pages` | CMS pages |
| GET  | `/public/content/announcements` | Active announcements |

## Auth

| Verb | Path | Body |
|------|------|------|
| POST | `/auth/login` | `{ email, password }` → `{ token, user }` |
| POST | `/auth/logout` | (auth) |
| GET  | `/auth/me` | (auth) → current user with roles/permissions |
| POST | `/auth/change-password` | `{ current_password, new_password, new_password_confirmation }` |

## Patients — `patients.*` permissions

| Verb | Path | Permission |
|------|------|------------|
| GET | `/patients` | `patients.view` |
| GET | `/patients/{id}` | `patients.view` |
| POST | `/patients` | `patients.create` |
| PATCH | `/patients/{id}` | `patients.update` |
| DELETE | `/patients/{id}` | `patients.delete` |
| GET | `/patients/{id}/duplicates` | `patients.view` |
| POST | `/patients/{id}/merge` | `patients.merge` |
| GET | `/patients/{id}/observations` | `emr.view` |

## Providers & Departments

| Verb | Path | Permission |
|------|------|------------|
| GET | `/providers` | — |
| GET | `/providers/{id}` | — |
| GET | `/providers/{id}/today` | `appointments.view` |
| GET | `/departments` | — |

## Scheduling

| Verb | Path | Permission |
|------|------|------------|
| GET | `/appointments` | `appointments.view` |
| GET | `/appointments/{id}` | `appointments.view` |
| POST | `/appointments` | `appointments.create` |
| POST | `/appointments/{id}/check-in` | `appointments.update` |
| POST | `/appointments/{id}/start-consult` | `emr.consult` |
| POST | `/appointments/{id}/complete` | `emr.consult` |
| POST | `/appointments/{id}/cancel` | `appointments.update` |
| POST | `/appointments/{id}/no-show` | `appointments.update` |
| GET | `/slots?provider_id=&date=` | `appointments.view` |

## Clinical (EMR)

| Verb | Path | Permission |
|------|------|------------|
| GET | `/encounters` | `emr.view` |
| GET | `/encounters/{id}` | `emr.view` |
| POST | `/encounters` | `emr.consult` |
| PATCH | `/encounters/{id}` | `emr.consult` |
| POST | `/encounters/{id}/close` | `emr.consult` |
| POST | `/encounters/{id}/vitals` | `emr.vitals` |
| GET | `/encounters/{id}/observations` | `emr.view` |
| GET | `/prescriptions` | `rx.view` |
| POST | `/prescriptions` | `rx.issue` |

**Vitals body example** (LOINC-coded observations are created server-side):

```json
POST /encounters/42/vitals
{
  "systolic": 128, "diastolic": 82,
  "heart_rate": 76, "temperature_c": 37.1,
  "spo2": 98, "respiratory_rate": 16,
  "weight_kg": 72.5, "height_cm": 174
}
```

## Pharmacy

| Verb | Path | Permission |
|------|------|------------|
| GET | `/pharmacy/medications` | `pharmacy.view` |
| GET | `/pharmacy/dispenses` | `pharmacy.view` |
| GET | `/pharmacy/dispenses/{id}` | `pharmacy.view` |
| POST | `/pharmacy/dispenses` | `pharmacy.dispense` |

**Dispense body**:

```json
POST /pharmacy/dispenses
{
  "patient_id": 12,
  "prescription_id": 87,
  "items": [
    { "medication_id": 3, "quantity": 10 },
    { "medication_id": 7, "quantity": 1 }
  ]
}
```

The server automatically selects batches by FEFO, refuses expired stock, writes `stock_movements` per batch, and emits `StockLow` if any SKU drops below its reorder threshold.

## Laboratory

| Verb | Path | Permission |
|------|------|------------|
| GET | `/lab/tests` | `lab.view` |
| GET | `/lab/orders` | `lab.view` |
| GET | `/lab/orders/{id}` | `lab.view` |
| POST | `/lab/orders` | `lab.order` |
| POST | `/lab/orders/{id}/collect-sample` | `lab.collect` |
| POST | `/lab/order-tests/{id}/result` | `lab.result` |
| POST | `/lab/results/{id}/verify` | `lab.verify` |

## Admission / IPD

| Verb | Path | Permission |
|------|------|------------|
| GET | `/admissions` | `ipd.view` |
| GET | `/admissions/{id}` | `ipd.view` |
| POST | `/admissions` | `ipd.admit` |
| POST | `/admissions/{id}/transfer-bed` | `ipd.transfer` |
| POST | `/admissions/{id}/discharge` | `ipd.discharge` |
| GET | `/beds` | `ipd.view` |
| GET | `/beds/occupancy` | `ipd.view` |
| PATCH | `/beds/{id}/status` | `ipd.bed.update` |

## Billing

| Verb | Path | Permission |
|------|------|------------|
| GET | `/billing/invoices` | `billing.view` |
| GET | `/billing/invoices/{id}` | `billing.view` |
| POST | `/billing/invoices/{id}/refresh` | `billing.update` |
| POST | `/billing/invoices/{id}/finalise` | `billing.finalise` |
| POST | `/billing/invoices/{id}/discount` | `billing.discount` |
| POST | `/billing/payments` | `billing.collect` |

## Admin

| Verb | Path | Permission |
|------|------|------------|
| GET | `/admin/users` | `admin.access` |
| POST | `/admin/users` | `admin.users.manage` |
| PATCH | `/admin/users/{id}` | `admin.users.manage` |
| POST | `/admin/users/{id}/reset-password` | `admin.users.manage` |
| GET | `/admin/roles` | `admin.access` |
| GET | `/admin/permissions` | `admin.access` |
| GET | `/admin/facilities` | `admin.access` |
| GET | `/admin/charge-catalog` | `admin.access` |
| GET | `/admin/audit-logs` | `admin.audit.view` |

## Rate limits

- Public endpoints: 60 req/min per IP
- Auth endpoints: 10 req/min per IP
- Authenticated endpoints: 300 req/min per user

Rate limit headers (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`) are returned on every request.
