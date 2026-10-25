<?php

namespace App\Http\Middleware;

use App\Models\Identity\Facility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active Facility for the request so queries can be
 * tenant-scoped. Tries (in order):
 *   1. X-Facility-Id header
 *   2. facility_id query param
 *   3. Authenticated user's facility_id
 *   4. The first/only facility (single-tenant mode)
 *
 * The resolved facility is stored in the service container as `facility`
 * and available to controllers via app('facility').
 */
class EnsureFacilityContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $facility = null;

        if ($headerId = $request->header('X-Facility-Id')) {
            $facility = Facility::find($headerId);
        } elseif ($queryId = $request->query('facility_id')) {
            $facility = Facility::find($queryId);
        } elseif ($user = $request->user()) {
            $facility = $user->facility_id ? Facility::find($user->facility_id) : null;
        }

        $facility ??= Facility::where('is_active', true)->first();

        if ($facility) {
            app()->instance('facility', $facility);
            config()->set('app.timezone', $facility->timezone ?? config('app.timezone'));
        }

        return $next($request);
    }
}
