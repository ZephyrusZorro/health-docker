import { api, unwrap } from './client';
import type {
  Appointment, Patient, Provider, Encounter, Prescription,
  Invoice, Medication, Dispense, LabOrder, LabTest, Bed, Admission,
  User, Department, Facility, ChargeCatalogItem, AuditEntry, Observation,
  Paginated, LoginResponse, MeResponse,
} from '@/types/api';

// ----- Auth ---------------------------------------------------------------
export const authApi = {
  login: (email: string, password: string) =>
    unwrap<LoginResponse>(api.post('/auth/login', { email, password })),
  logout: () => api.post('/auth/logout'),
  me: () => unwrap<MeResponse>(api.get('/auth/me')),
  changePassword: (current: string, next: string) =>
    api.post('/auth/change-password', {
      current_password: current, new_password: next, new_password_confirmation: next,
    }),
};

// ----- Patients -----------------------------------------------------------
export const patientsApi = {
  list: (params?: Record<string, string | number | boolean>) =>
    api.get<Paginated<Patient>>('/patients', { params }).then((r) => r.data),
  get: (id: number | string) => unwrap<Patient>(api.get(`/patients/${id}`)),
  create: (body: Partial<Patient>) => unwrap<Patient>(api.post('/patients', body)),
  update: (id: number, body: Partial<Patient>) => unwrap<Patient>(api.patch(`/patients/${id}`, body)),
  destroy: (id: number) => api.delete(`/patients/${id}`),
  duplicates: (id: number) => unwrap<Patient[]>(api.get(`/patients/${id}/duplicates`)),
  observations: (id: number, code?: string) =>
    unwrap<Observation[]>(api.get(`/patients/${id}/observations`, { params: code ? { code } : {} })),
};

// ----- Providers / Departments ------------------------------------------
export const providersApi = {
  list: () => api.get<Paginated<Provider>>('/providers').then((r) => r.data),
  get: (id: number) => unwrap<Provider>(api.get(`/providers/${id}`)),
  todayDashboard: (id: number) =>
    unwrap<{ appointments: Appointment[]; waiting: Appointment[]; admitted: Patient[] }>(
      api.get(`/providers/${id}/today`),
    ),
};
export const departmentsApi = {
  list: () => api.get<Paginated<Department>>('/departments').then((r) => r.data),
};

// ----- Appointments ------------------------------------------------------
export const appointmentsApi = {
  list: (params?: Record<string, any>) =>
    api.get<Paginated<Appointment>>('/appointments', { params }).then((r) => r.data),
  get: (id: number) => unwrap<Appointment>(api.get(`/appointments/${id}`)),
  create: (body: Partial<Appointment>) => unwrap<Appointment>(api.post('/appointments', body)),
  checkIn: (id: number) => unwrap<Appointment>(api.post(`/appointments/${id}/check-in`)),
  startConsult: (id: number) => unwrap<Appointment>(api.post(`/appointments/${id}/start-consult`)),
  complete: (id: number) => unwrap<Appointment>(api.post(`/appointments/${id}/complete`)),
  cancel: (id: number, reason?: string) =>
    api.post(`/appointments/${id}/cancel`, reason ? { reason } : {}),
  markNoShow: (id: number) => api.post(`/appointments/${id}/no-show`),
};

export const slotsApi = {
  list: (providerId: number, date: string) =>
    unwrap<Array<{ id: number; start_time: string; end_time: string; capacity: number; booked_count: number; available: boolean }>>(
      api.get('/slots', { params: { provider_id: providerId, date } }),
    ),
};

// ----- Encounters (EMR) --------------------------------------------------
export const encountersApi = {
  list: (params?: Record<string, any>) =>
    api.get<Paginated<Encounter>>('/encounters', { params }).then((r) => r.data),
  get: (id: number) => unwrap<Encounter>(api.get(`/encounters/${id}`)),
  create: (body: Partial<Encounter>) => unwrap<Encounter>(api.post('/encounters', body)),
  update: (id: number, body: Partial<Encounter>) =>
    unwrap<Encounter>(api.patch(`/encounters/${id}`, body)),
  close: (id: number) => unwrap<Encounter>(api.post(`/encounters/${id}/close`)),
  recordVitals: (id: number, vitals: Record<string, number>) =>
    api.post(`/encounters/${id}/vitals`, vitals),
  observations: (id: number) => unwrap<Observation[]>(api.get(`/encounters/${id}/observations`)),
};

// ----- Prescriptions -----------------------------------------------------
export const prescriptionsApi = {
  list: (params?: Record<string, any>) =>
    api.get<Paginated<Prescription>>('/prescriptions', { params }).then((r) => r.data),
  get: (id: number) => unwrap<Prescription>(api.get(`/prescriptions/${id}`)),
  create: (body: Partial<Prescription> & { items: any[] }) =>
    unwrap<Prescription>(api.post('/prescriptions', body)),
};

// ----- Pharmacy ----------------------------------------------------------
export const pharmacyApi = {
  medications: (params?: Record<string, any>) =>
    api.get<Paginated<Medication>>('/pharmacy/medications', { params }).then((r) => r.data),
  dispenses: (params?: Record<string, any>) =>
    api.get<Paginated<Dispense>>('/pharmacy/dispenses', { params }).then((r) => r.data),
  dispense: (body: { patient_id: number; prescription_id?: number; encounter_id?: number; items: Array<{ medication_id: number; quantity: number }> }) =>
    unwrap<Dispense>(api.post('/pharmacy/dispenses', body)),
  getDispense: (id: number) => unwrap<Dispense>(api.get(`/pharmacy/dispenses/${id}`)),
};

// ----- Laboratory --------------------------------------------------------
export const labApi = {
  tests: (params?: Record<string, any>) =>
    api.get<Paginated<LabTest>>('/lab/tests', { params }).then((r) => r.data),
  orders: (params?: Record<string, any>) =>
    api.get<Paginated<LabOrder>>('/lab/orders', { params }).then((r) => r.data),
  getOrder: (id: number) => unwrap<LabOrder>(api.get(`/lab/orders/${id}`)),
  createOrder: (body: { patient_id: number; encounter_id?: number; test_ids: number[]; priority?: string; clinical_note?: string }) =>
    unwrap<LabOrder>(api.post('/lab/orders', body)),
  collectSample: (orderId: number, body: { sample_type: string; container?: string; notes?: string }) =>
    api.post(`/lab/orders/${orderId}/collect-sample`, body),
  storeResult: (orderTestId: number, body: { value_numeric?: number; value_text?: string; unit?: string; interpretation?: string }) =>
    api.post(`/lab/order-tests/${orderTestId}/result`, body),
  verifyResult: (resultId: number) => api.post(`/lab/results/${resultId}/verify`),
};

// ----- Admissions & Beds -------------------------------------------------
export const admissionsApi = {
  list: (params?: Record<string, any>) =>
    api.get<Paginated<Admission>>('/admissions', { params }).then((r) => r.data),
  get: (id: number) => unwrap<Admission>(api.get(`/admissions/${id}`)),
  admit: (body: { patient_id: number; admitting_provider_id: number; bed_id: number; admission_reason: string; admission_type: string }) =>
    unwrap<Admission>(api.post('/admissions', body)),
  transfer: (id: number, body: { new_bed_id: number; reason?: string }) =>
    api.post(`/admissions/${id}/transfer-bed`, body),
  discharge: (id: number, body: { discharge_summary?: string }) =>
    api.post(`/admissions/${id}/discharge`, body),
};
export const bedsApi = {
  list: (params?: Record<string, any>) =>
    api.get<Paginated<Bed>>('/beds', { params }).then((r) => r.data),
  occupancy: () => unwrap<any>(api.get('/beds/occupancy')),
  updateStatus: (id: number, status: string) =>
    api.patch(`/beds/${id}/status`, { status }),
};

// ----- Billing -----------------------------------------------------------
export const billingApi = {
  invoices: (params?: Record<string, any>) =>
    api.get<Paginated<Invoice>>('/billing/invoices', { params }).then((r) => r.data),
  getInvoice: (id: number) => unwrap<Invoice>(api.get(`/billing/invoices/${id}`)),
  refresh: (id: number) => api.post(`/billing/invoices/${id}/refresh`),
  finalise: (id: number) => api.post(`/billing/invoices/${id}/finalise`),
  applyDiscount: (id: number, body: { amount: number; reason: string }) =>
    api.post(`/billing/invoices/${id}/discount`, body),
  payment: (body: { invoice_id: number; amount: number; method: string; reference?: string }) =>
    api.post('/billing/payments', body),
};

// ----- Admin -------------------------------------------------------------
export const adminApi = {
  users: (params?: Record<string, any>) =>
    api.get<Paginated<User>>('/admin/users', { params }).then((r) => r.data),
  createUser: (body: any) => unwrap<User>(api.post('/admin/users', body)),
  updateUser: (id: number, body: any) => unwrap<User>(api.patch(`/admin/users/${id}`, body)),
  resetPassword: (id: number, password: string) =>
    api.post(`/admin/users/${id}/reset-password`, { password }),
  roles: () => unwrap<any[]>(api.get('/admin/roles')),
  permissions: () => unwrap<Record<string, any[]>>(api.get('/admin/permissions')),
  facilities: () => unwrap<Facility[]>(api.get('/admin/facilities')),
  chargeCatalog: (params?: Record<string, any>) =>
    api.get<Paginated<ChargeCatalogItem>>('/admin/charge-catalog', { params }).then((r) => r.data),
  auditLogs: (params?: Record<string, any>) =>
    api.get<Paginated<AuditEntry>>('/admin/audit-logs', { params }).then((r) => r.data),
};

// ----- Public (no auth) --------------------------------------------------
export const publicApi = {
  doctors: () => unwrap<Provider[]>(api.get('/public/doctors')),
  departments: () => unwrap<Department[]>(api.get('/public/departments')),
  availability: (providerId: number, date: string) =>
    unwrap<any[]>(api.get('/public/availability', { params: { provider_id: providerId, date } })),
  bookAppointment: (body: any) => unwrap<Appointment>(api.post('/public/appointments', body)),
  appointmentStatus: (appointmentNumber: string, phone: string) =>
    unwrap<any>(api.post('/public/appointments/status', { appointment_number: appointmentNumber, phone })),
  pages: () => unwrap<any[]>(api.get('/public/content/pages')),
  announcements: () => unwrap<any[]>(api.get('/public/content/announcements')),
};
