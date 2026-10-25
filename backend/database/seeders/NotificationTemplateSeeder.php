<?php

namespace Database\Seeders;

use App\Models\Audit\NotificationTemplate;
use App\Models\Identity\Facility;
use Illuminate\Database\Seeder;

/**
 * Seeds the notification templates (SRS §4.13). Templates use {{variable}}
 * placeholders that NotificationDispatcher fills in at send time.
 *
 * Schema is one row per (facility, code, channel, language) — so a single
 * notification with three channels produces three rows.
 */
class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::firstOrFail();

        $templates = [
            // Appointment lifecycle
            [
                'code'    => 'appointment.confirmed',
                'name'    => 'Appointment confirmation',
                'subject' => 'Your appointment at {{facility_name}} is confirmed',
                'body'    => "Hello {{patient_name}},\n\nYour appointment with {{doctor_name}} is confirmed for {{appointment_datetime}}.\n\nAppointment no.: {{appointment_number}}\nToken: {{token_number}}\n\nThank you,\n{{facility_name}}",
                'channels' => ['email', 'sms'],
            ],
            [
                'code'    => 'appointment.reminder',
                'name'    => '60-min reminder',
                'subject' => 'Your appointment is in an hour',
                'body'    => "Hi {{patient_name}}, this is a reminder that you have an appointment with {{doctor_name}} at {{scheduled_at}}. Token: {{token}}.",
                'channels' => ['sms', 'email'],
            ],
            [
                'code'    => 'appointment.cancelled',
                'name'    => 'Appointment cancellation',
                'subject' => 'Your appointment has been cancelled',
                'body'    => "Hi {{patient_name}}, your appointment {{appointment_number}} has been cancelled. {{reason}}",
                'channels' => ['email', 'sms'],
            ],

            // Lab
            [
                'code'    => 'lab.result_ready',
                'name'    => 'Lab result ready',
                'subject' => 'Your test results are ready',
                'body'    => "Hi {{patient_name}}, your lab results for order {{order_number}} are now available. Please log in or contact your doctor for details.",
                'channels' => ['email', 'sms'],
            ],

            // Billing
            [
                'code'    => 'billing.payment_received',
                'name'    => 'Payment receipt',
                'subject' => 'Payment received — receipt {{receipt_number}}',
                'body'    => "Hi {{patient_name}}, we've received your payment of {{amount}}. Receipt no. {{receipt_number}}.",
                'channels' => ['email'],
            ],

            // Pharmacy — staff
            [
                'code'    => 'pharmacy.daily_digest',
                'name'    => 'Pharmacy daily digest',
                'subject' => 'Pharmacy: {{low_stock_count}} low-stock, {{expiring_count}} expiring',
                'body'    => "Daily pharmacy summary:\n- {{low_stock_count}} medications below reorder level\n- {{expiring_count}} batches expiring soon\n\nReview the pharmacy module for details.",
                'channels' => ['email'],
            ],
            [
                'code'    => 'stock.low',
                'name'    => 'Low-stock alert',
                'subject' => 'Stock alert: {{medication}}',
                'body'    => "Low stock alert: {{medication}} is at {{current_stock}} units (reorder level {{reorder_level}}). Please reorder.",
                'channels' => ['email', 'in_app'],
            ],

            // IPD
            [
                'code'    => 'ipd.discharge_ready',
                'name'    => 'Discharge ready',
                'subject' => 'Your discharge is ready',
                'body'    => "{{patient_name}}, your discharge summary is ready. Please settle the final bill at the cashier to complete the process.",
                'channels' => ['email', 'sms'],
            ],

            // Housekeeping (internal)
            [
                'code'    => 'housekeeping.bed_cleanup_sla',
                'name'    => 'Bed cleanup SLA breach',
                'subject' => '{{count}} bed(s) over cleanup SLA',
                'body'    => "{{count}} beds have been in cleaning state past the SLA. Please reassign housekeeping.",
                'channels' => ['email', 'push'],
            ],
        ];

        foreach ($templates as $t) {
            foreach ($t['channels'] as $channel) {
                NotificationTemplate::updateOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'code'        => $t['code'],
                        'channel'     => $channel,
                        'language'    => 'en',
                    ],
                    [
                        'facility_id' => $facility->id,
                        'name'        => $t['name'],
                        'subject'     => $t['subject'] ?? null,
                        'body'        => $t['body'],
                        'is_active'   => true,
                    ],
                );
            }
        }
    }
}
