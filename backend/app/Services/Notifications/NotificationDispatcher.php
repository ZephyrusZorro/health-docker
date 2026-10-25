<?php

namespace App\Services\Notifications;

use App\Models\Audit\MessageLog;
use App\Models\Audit\NotificationTemplate;
use App\Models\Identity\Facility;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pluggable notification pipeline — SRS §4.13.
 *
 * Two entry points (intentional — different callers use different shapes):
 *   - send(...)     for console commands: pass a User/Patient as recipient
 *   - dispatch(...) for listeners: pass an array {name, phone, email, user_id?}
 *
 * The pipeline:
 *   1. Resolve a NotificationTemplate per (code, channel) — schema is one
 *      row per (facility, code, channel, language), so each channel renders
 *      its own template.
 *   2. Render subject + body with the provided payload (`{{key}}` syntax).
 *   3. Route to the active driver per channel (sms / whatsapp / email / push).
 *   4. Write a MessageLog row for delivery auditability.
 *
 * Drivers default to 'log' in dev — nothing actually sends, but everything
 * is recorded so the UI can demo the flow end-to-end (see Mailpit too).
 */
class NotificationDispatcher
{
    /**
     * Console-friendly entry point. `recipient` may be a User/Patient model or
     * an array; `data` is the template payload; `channels` defaults to email.
     */
    public function send(
        string $template,
        Model|array $recipient,
        array $data = [],
        array $channels = ['email'],
    ): void {
        $r = $this->resolveRecipient($recipient);
        $this->dispatch($template, $r, $data, $channels);
    }

    /**
     * Direct dispatch — listeners and services use this directly.
     *
     * @param array{name?:string|null, phone?:string|null, email?:string|null, user_id?:int|null, facility_id?:int|null} $recipient
     * @param array<string,mixed> $payload
     * @param array<int,string>   $channels
     */
    public function dispatch(
        string $templateKey,
        array $recipient,
        array $payload = [],
        array $channels = ['email'],
    ): void {
        $facilityId = $recipient['facility_id'] ?? Facility::value('id');

        foreach ($channels as $channel) {
            // One row per channel per (facility, code, language).
            $template = NotificationTemplate::where('code', $templateKey)
                ->where('channel', $channel)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('facility_id', $facilityId)->orWhereNull('facility_id'))
                ->first();

            if (! $template) {
                Log::warning("Notification template '{$templateKey}/{$channel}' not found — skipping.");
                continue;
            }

            $subject = $this->render((string) ($template->subject ?? ''), $payload);
            $body    = $this->render((string) $template->body, $payload);
            $to      = $this->channelAddress($channel, $recipient);
            if (! $to) continue;

            $log = MessageLog::create([
                'facility_id'   => $facilityId,
                'template_code' => $templateKey,
                'channel'       => $channel,
                'recipient'     => $to,
                'subject'       => $subject ?: null,
                'body'          => $body,
                'status'        => 'queued',
            ]);

            try {
                match ($channel) {
                    'email'    => $this->sendEmail($recipient, $subject, $body),
                    'sms'      => $this->sendSms($recipient, $body),
                    'whatsapp' => $this->sendWhatsApp($recipient, $body),
                    'push'     => $this->sendPush($recipient, $subject, $body),
                    'in_app'   => null, // visible via /notifications endpoint
                    default    => throw new \InvalidArgumentException("Unknown channel: {$channel}"),
                };
                $log->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (\Throwable $e) {
                $log->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                Log::error("Notification send failed [{$channel}]: {$e->getMessage()}");
            }
        }
    }

    /** Coerce a User/Patient model into the canonical recipient array. */
    private function resolveRecipient(Model|array $recipient): array
    {
        if (is_array($recipient)) return $recipient;

        $arr = ['name' => $recipient->name ?? null];
        if (isset($recipient->email)) $arr['email'] = $recipient->email;
        if (isset($recipient->phone)) $arr['phone'] = $recipient->phone;
        if ($recipient instanceof User) {
            $arr['user_id']     = $recipient->id;
            $arr['facility_id'] = $recipient->facility_id;
        }
        if (! isset($arr['name']) && isset($recipient->first_name)) {
            $arr['name'] = trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? ''));
        }
        return array_filter($arr, fn ($v) => $v !== null && $v !== '');
    }

    private function channelAddress(string $channel, array $recipient): ?string
    {
        return match ($channel) {
            'email'                   => $recipient['email'] ?? null,
            'sms', 'whatsapp'         => $recipient['phone'] ?? null,
            'push', 'in_app'          => isset($recipient['user_id']) ? "user:{$recipient['user_id']}" : null,
            default                   => null,
        };
    }

    private function render(string $template, array $payload): string
    {
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $template = str_replace('{{' . $key . '}}', (string) $value, $template);
            }
        }
        return $template;
    }

    private function sendEmail(array $recipient, string $subject, string $body): void
    {
        if (empty($recipient['email'])) return;
        Mail::raw($body, function ($message) use ($recipient, $subject) {
            $message->to($recipient['email'], $recipient['name'] ?? null)
                    ->subject($subject ?: '(no subject)');
        });
    }

    private function sendSms(array $recipient, string $body): void
    {
        $driver = config('hms.integrations.sms_driver', 'log');
        Log::info("[SMS to {$recipient['phone']}]", ['body' => $body, 'driver' => $driver]);
    }

    private function sendWhatsApp(array $recipient, string $body): void
    {
        $driver = config('hms.integrations.whatsapp_driver', 'log');
        Log::info("[WhatsApp to {$recipient['phone']}]", ['body' => $body, 'driver' => $driver]);
    }

    private function sendPush(array $recipient, string $subject, string $body): void
    {
        $userId = $recipient['user_id'] ?? null;
        Log::info("[Push to user {$userId}]", ['subject' => $subject, 'body' => $body]);
    }
}
