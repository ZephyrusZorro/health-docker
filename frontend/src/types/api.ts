// ---- Generic pagination envelope --------------------------------------
export interface Paginated<T> {
  data: T[];
  links?: { first: string; last: string; prev: string | null; next: string | null };
  meta?: { current_page: number; last_page: number; per_page: number; total: number };
}

// ---- Identity ----------------------------------------------------------
export interface Facility {
  id: number; code: string; name: string;
  legal_name?: string; phone?: string; email?: string;
  address?: Record<string, string>; timezone?: string;
  default_currency?: string; is_active: boolean;
}

export interface Department {
  id: number; code: string; name: string; icon?: string;
  description?: string; sort_order: number; is_active: boolean;
  providers?: Provider[];
}

export interface User {
  id: number; name: string; email: string; phone?: string;
  user_type: 'staff' | 'patient' | 'system';
  is_active: boolean; last_login_at?: string;
  avatar_url?: string;
  roles?: Array<{ id: number; name: string; display_name?: string }>;
  permissions?: Array<{ name: string }>;
  facility?: Facility; provider?: Provider; staff?: any;
}

export interface Provider {
  id: number; user_id: number;
  department_id?: number;
  provider_type: 'doctor' | 'nurse' | 'lab_technician' | 'pharmacist' | 'other';
  registration_number?: string;
  qualification?: string; specialization?: string;
  years_of_experience?: number;
  consultation_fee?: number | string;
  bio?: string; languages?: string[];
  is_available_online: boolean;
  user?: User; department?: Department;
}

export interface Patient {
  id: number; uhid: string;
  first_name: string; last_name?: string; preferred_name?: string;
  full_name?: string;
  gender: 'male' | 'female' | 'other' | 'unknown';
  date_of_birth: string; age?: number;
  blood_group?: string; marital_status?: string; occupation?: string;
  phone?: string; phone_alt?: string; email?: string;
  address?: Record<string, string>;
  emergency_contact?: Record<string, string>;
  payer_type: 'self' | 'insurance' | 'corporate' | 'government' | 'charity';
  insurance_provider?: string; insurance_number?: string;
  referred_by?: string;
  is_vip: boolean; is_deceased: boolean;
  registered_at: string;
}

// ---- Scheduling --------------------------------------------------------
export type AppointmentStatus =
  | 'booked' | 'confirmed' | 'checked_in' | 'in_consultation'
  | 'completed' | 'cancelled' | 'no_show' | 'rescheduled';

export interface VisitType {
  id: number; code: string; name: string; default_duration_minutes: number; color?: string;
}

export interface Appointment {
  id: number; appointment_number: string;
  patient_id: number; provider_id: number; slot_id?: number;
  visit_type_id?: number;
  scheduled_start: string; scheduled_end: string;
  status: AppointmentStatus; token_number?: string;
  booking_source?: string; fee_amount?: number;
  chief_complaint?: string; notes?: string;
  checked_in_at?: string; consult_started_at?: string; consult_ended_at?: string;
  patient?: Patient; provider?: Provider; visit_type?: VisitType;
}

// ---- Clinical ----------------------------------------------------------
export interface Encounter {
  id: number; appointment_id?: number;
  patient_id: number; provider_id: number;
  encounter_type: 'outpatient' | 'inpatient' | 'emergency';
  started_at: string; ended_at?: string;
  status: 'open' | 'closed';
  chief_complaint?: string; clinical_summary?: string;
  patient?: Patient; provider?: Provider;
  observations?: Observation[];
  conditions?: Condition[];
  prescriptions?: Prescription[];
}

export interface Observation {
  id: number; encounter_id?: number; patient_id: number;
  category: string; code: string; code_system?: string;
  display_name: string;
  value_numeric?: number | string | null; value_text?: string | null;
  unit?: string; recorded_at: string;
}

export interface Condition {
  id: number; patient_id: number; encounter_id?: number;
  code: string; code_system?: string; display_name: string;
  clinical_status?: string; verification_status?: string;
  onset_date?: string; recorded_at: string;
}

export interface Prescription {
  id: number; prescription_number: string;
  patient_id: number; provider_id: number; encounter_id?: number;
  issued_at: string; status: string; notes?: string;
  items?: PrescriptionItem[];
  patient?: Patient; provider?: Provider;
}

export interface PrescriptionItem {
  id: number; prescription_id: number; medication_id: number;
  dosage: string; frequency: string; duration_days: number;
  quantity: number; route?: string; instructions?: string;
  medication?: Medication;
}

// ---- Pharmacy ----------------------------------------------------------
export interface Medication {
  id: number; code: string;
  generic_name: string; brand_name?: string; strength?: string; form?: string;
  category?: string; manufacturer?: string;
  default_tax_percent?: number;
  is_prescription_required: boolean; is_controlled: boolean;
  storage_instructions?: string; is_active: boolean;
  display_name?: string;
}

export interface Dispense {
  id: number; dispense_number: string;
  facility_id: number; patient_id: number;
  prescription_id?: number; encounter_id?: number;
  dispensed_at: string; total_amount: number; status: string;
  items?: DispenseItem[];
  patient?: Patient;
}

export interface DispenseItem {
  id: number; dispense_id: number; medication_id: number;
  medicine_batch_id: number;
  quantity: number; unit_price: number; line_total: number;
  instructions?: string;
  medication?: Medication;
}

// ---- Laboratory --------------------------------------------------------
export interface LabTest {
  id: number; code: string; name: string; category: string;
  sample_type?: string; unit?: string;
  reference_min?: number; reference_max?: number; reference_text?: string;
  price: number; turnaround_hours?: number;
  is_panel: boolean; is_active: boolean;
}

export interface LabOrder {
  id: number; order_number: string;
  patient_id: number; encounter_id?: number;
  ordered_by: number; ordered_at: string;
  priority: string; status: string; completed_at?: string;
  clinical_note?: string;
  tests?: LabOrderTest[]; patient?: Patient;
}

export interface LabOrderTest {
  id: number; lab_order_id: number; lab_test_id: number;
  price: number; status: string;
  lab_test?: LabTest; result?: LabResult;
}

export interface LabResult {
  id: number; lab_order_test_id: number;
  value_numeric?: number; value_text?: string; unit?: string;
  is_abnormal: boolean; abnormal_flag?: string; interpretation?: string;
  entered_by: number; entered_at: string;
  verified_by?: number; verified_at?: string; status: string;
  files?: Array<{ id: number; file_path: string; mime_type: string }>;
}

// ---- Admission ---------------------------------------------------------
export interface Ward {
  id: number; code: string; name: string; floor?: string; building?: string;
  is_active: boolean;
}
export interface Room {
  id: number; ward_id: number; code: string; name: string;
  room_category: string; daily_rate: number;
}
export interface Bed {
  id: number; room_id: number; code: string; label: string;
  status: 'available' | 'occupied' | 'cleaning' | 'maintenance' | 'reserved';
  is_active: boolean;
  room?: Room;
}
export interface Admission {
  id: number; admission_number: string;
  patient_id: number; admitting_provider_id: number;
  admitted_at: string; discharged_at?: string;
  status: 'active' | 'discharged';
  admission_reason: string; admission_type: string;
  patient?: Patient;
}

// ---- Billing -----------------------------------------------------------
export interface Invoice {
  id: number; invoice_number: string;
  patient_id: number;
  encounter_id?: number; admission_id?: number;
  status: 'draft' | 'finalised' | 'paid' | 'partially_paid' | 'void';
  issue_date: string; due_date?: string;
  currency: string;
  subtotal: number; tax_amount: number; discount_amount?: number;
  total_amount: number; amount_paid: number; amount_due: number;
  items?: InvoiceItem[]; payments?: Payment[];
  patient?: Patient;
}
export interface InvoiceItem {
  id: number; invoice_id: number;
  category: string; description: string;
  quantity: number; unit_price: number;
  tax_percent?: number; line_total: number;
  source_type?: string; source_id?: number;
}
export interface Payment {
  id: number; invoice_id: number; patient_id: number;
  amount: number; method: string; status: string;
  received_at: string; receipt_number?: string;
}

// ---- Admin -------------------------------------------------------------
export interface ChargeCatalogItem {
  id: number; facility_id: number; code: string; name: string;
  category: string; unit_price: number; tax_percent?: number;
  description?: string; is_package: boolean; is_active: boolean;
}
export interface AuditEntry {
  id: number; user_id?: number; action: string;
  auditable_type: string; auditable_id: number;
  before?: Record<string, any>; after?: Record<string, any>;
  ip_address?: string; user_agent?: string; occurred_at: string;
  user?: { id: number; name: string; email: string };
}

// ---- Auth --------------------------------------------------------------
export interface LoginResponse {
  token: string;
  user: User;
}
export interface MeResponse extends User {}
