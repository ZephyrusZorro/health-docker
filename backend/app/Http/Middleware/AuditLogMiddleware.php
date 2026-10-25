<?php

namespace App\Http\Middleware;

use App\Models\Audit\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every non-GET API call as an activity log for the authenticated
 * user. Sensitive field values (password, tokens) are redacted before storage.
 *
 * Storage uses the `activity_logs` table — `description` is the human
 * summary; `properties` JSON carries the structured request/response detail.
 *
 * SRS §12.3: "IP and device logging for admin-level operations".
 */
class AuditLogMiddleware
{
    /**
     * Field names whose values must be scrubbed before they hit the audit log.
     */
    protected array $redactKeys = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'api_key', 'mfa_secret', 'otp', 'secret',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip reads — only audit mutations.
        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $response;
        }

        try {
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'log_name'    => 'api',
                'description' => sprintf('%s %s', $request->method(), $request->path()),
                'properties'  => [
                    'method'     => $request->method(),
                    'path'       => $request->path(),
                    'route'      => optional($request->route())->getName(),
                    'ip'         => $request->ip(),
                    'agent'      => substr((string) $request->userAgent(), 0, 250),
                    'status'     => $response->getStatusCode(),
                    'body'       => $this->redact($request->all()),
                ],
            ]);
        } catch (\Throwable $e) {
            // Never let an audit failure break the request.
            logger()->warning("AuditLogMiddleware failed: {$e->getMessage()}");
        }

        return $response;
    }

    private function redact(array $data): array
    {
        array_walk_recursive($data, function (&$v, $k) {
            if (in_array($k, $this->redactKeys, true)) {
                $v = '***REDACTED***';
            }
        });
        return $data;
    }
}
