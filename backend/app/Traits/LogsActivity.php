<?php

namespace App\Traits;

use App\Models\Audit\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Attach to any model whose create / update / delete should be audit-logged.
 * SRS §9: "Every clinical edit is attributable to a named user with timestamp."
 * SRS §12.3: "Audit logs for all sensitive actions."
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($m) => $m->writeAudit('create'));
        static::updated(fn ($m) => $m->writeAudit('update'));
        static::deleted(fn ($m) => $m->writeAudit('delete'));
        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($m) => $m->writeAudit('restore'));
        }
    }

    protected function auditIgnoredAttributes(): array
    {
        return [
            'password', 'remember_token', 'mfa_secret',
            'updated_at', 'last_login_at', 'last_login_ip',
        ];
    }

    protected function auditCategory(): string
    {
        $ns = explode('\\', static::class);
        return strtolower($ns[count($ns) - 2] ?? 'general');
    }

    protected function writeAudit(string $action): void
    {
        if (! class_exists(AuditLog::class)) return;

        $ignored = $this->auditIgnoredAttributes();

        $old = $action === 'create' ? null
            : array_diff_key($this->getOriginal(), array_flip($ignored));
        $new = $action === 'delete' ? null
            : array_diff_key($this->getAttributes(), array_flip($ignored));

        $user = Auth::user();

        try {
            AuditLog::create([
                'facility_id'    => $user->facility_id ?? ($this->facility_id ?? null),
                'user_id'        => $user?->id,
                'user_email'     => $user?->email,
                'user_role'      => $user?->roles?->first()?->name,
                'event'          => static::auditEventName($action),
                'category'       => $this->auditCategory(),
                'severity'       => $action === 'delete' ? 'warning' : 'info',
                'auditable_type' => static::class,
                'auditable_id'   => $this->getKey(),
                'old_values'     => $old,
                'new_values'     => $new,
                'ip_address'     => Request::ip(),
                'user_agent'     => Request::userAgent(),
                'request_id'     => Request::header('X-Request-Id'),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('audit_log_write_failed', [
                'error'  => $e->getMessage(),
                'class'  => static::class,
                'action' => $action,
            ]);
        }
    }

    protected static function auditEventName(string $action): string
    {
        return strtolower(class_basename(static::class)) . '.' . $action;
    }
}
