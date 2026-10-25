<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Audit\AuditLog;
use Illuminate\Http\Request;

/**
 * Audit log viewer (SRS §4.12, §12.3). Read-only. Filters by actor, event,
 * type, and date range. Records are immutable — no update/delete endpoints.
 */
class AuditLogController extends ApiController
{
    public function index(Request $request)
    {
        $q = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('user_id'),  fn ($q) => $q->where('user_id',  $request->user_id))
            ->when($request->filled('event'),    fn ($q) => $q->where('event',    $request->event))
            ->when($request->filled('action'),   fn ($q) => $q->where('event',    $request->action))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('type'),     fn ($q) => $q->where('auditable_type', $request->type))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
            ->when($request->filled('from'),     fn ($q) => $q->where('created_at', '>=', $request->from))
            ->when($request->filled('to'),       fn ($q) => $q->where('created_at', '<=', $request->to))
            ->latest('created_at');

        $logs = $q->paginate($request->integer('per_page', 50));

        // Frontend expects fields like `action`, `occurred_at`, `auditable_*`.
        // Inject those as derived values without mutating storage.
        $logs->getCollection()->transform(function (AuditLog $row) {
            $row->action      = $row->event;
            $row->occurred_at = $row->created_at;
            return $row;
        });

        return $this->ok($logs);
    }

    public function show(AuditLog $auditLog)
    {
        return $this->ok($auditLog->load('user:id,name,email'));
    }
}
