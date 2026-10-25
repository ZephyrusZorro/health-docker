<?php

/**
 * Nishat HMS — application-specific configuration.
 *
 * All business constants required by the SRS live here rather than scattered
 * through controllers, so auditors and ops can change policy without code.
 */
return [

    // -------------------------------------------------------------------------
    // Identity — UHID & token generation (SRS §4.1, §4.2)
    // -------------------------------------------------------------------------
    'identity' => [
        'uhid_prefix'   => env('HMS_UHID_PREFIX', 'NH'),
        'uhid_format'   => '{prefix}-{year}-{seq:6}',   // NH-2026-000001
        'token_prefix'  => env('HMS_TOKEN_PREFIX', 'T'),
        'token_format'  => '{prefix}{seq:3}',           // T001
    ],

    // -------------------------------------------------------------------------
    // Scheduling (SRS §4.2)
    // -------------------------------------------------------------------------
    'scheduling' => [
        'default_slot_minutes'      => 15,
        'max_advance_booking_days'  => 60,
        'reminder_hours'            => array_map('intval', explode(',', (string) env('HMS_APPOINTMENT_REMINDER_HOURS', '24,2'))),
        'no_show_grace_minutes'     => 15,
    ],

    // -------------------------------------------------------------------------
    // Pharmacy (SRS §4.7)
    // -------------------------------------------------------------------------
    'pharmacy' => [
        'dispense_strategy'     => 'fefo',           // First Expiry First Out
        'expiry_alert_days'     => 90,
        'low_stock_percentage'  => 20,               // alert when stock < 20% of reorder level
    ],

    // -------------------------------------------------------------------------
    // Billing (SRS §4.9)
    // -------------------------------------------------------------------------
    'billing' => [
        'default_currency'            => env('HMS_DEFAULT_CURRENCY', 'INR'),
        'discount_approval_threshold' => (float) env('HMS_DISCOUNT_APPROVAL_THRESHOLD', 20),
        'invoice_number_format'       => 'INV-{year}{month}-{seq:6}',
        'tax_inclusive'               => env('HMS_TAX_INCLUSIVE', false),
    ],

    // -------------------------------------------------------------------------
    // Wards & Beds (SRS §4.10)
    // -------------------------------------------------------------------------
    'wards' => [
        'cleaning_minutes_after_discharge' => (int) env('HMS_BED_CLEANING_MINUTES', 30),
    ],

    // -------------------------------------------------------------------------
    // Security (SRS §12)
    // -------------------------------------------------------------------------
    'security' => [
        'mfa_enforced'         => (bool) env('HMS_MFA_ENFORCED', false),
        'bcrypt_cost'          => 12,
        'session_idle_minutes' => 60,
        'audit_retention_days' => 2555,  // ~7 years (healthcare record retention)
    ],

    // -------------------------------------------------------------------------
    // Integrations (stubbed in MVP, pluggable per SRS §10)
    // -------------------------------------------------------------------------
    'integrations' => [
        'sms_driver'      => env('SMS_DRIVER', 'log'),
        'whatsapp_driver' => env('WHATSAPP_DRIVER', 'log'),
        'payment_gateway' => env('PAYMENT_GATEWAY', 'stub'),
        'chatbot'         => env('CHATBOT_PROVIDER', 'stub'),
    ],

    // -------------------------------------------------------------------------
    // FHIR (SRS §5.6 — ready from day one, exposed in Phase 3)
    // -------------------------------------------------------------------------
    'fhir' => [
        'version'        => 'R4',
        'base_url'       => env('FHIR_BASE_URL', '/api/v1/fhir'),
        'expose_export'  => env('FHIR_EXPOSE_EXPORT', false),
    ],
];
