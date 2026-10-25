<?php

namespace App\Listeners;

use App\Models\Audit\SystemEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;

/**
 * Catch-all listener that writes a SystemEvent row for every domain event.
 * Chronologically ordered, safely serialised event payload — this is the
 * substrate for SRS §6.2's event-driven architecture and §12.3's audit trail.
 */
class LogAuditEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(object $event): void
    {
        $reflect = new ReflectionClass($event);
        $payload = [];
        $aggregateType = null;
        $aggregateId   = null;

        foreach ($reflect->getProperties() as $prop) {
            $name  = $prop->getName();
            $value = $prop->getValue($event);

            if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                // First Eloquent model becomes the aggregate. Subsequent ones
                // are summarised in the payload.
                if ($aggregateType === null) {
                    $aggregateType = class_basename($value);
                    $aggregateId   = $value->getKey();
                }
                $payload[$name] = ['model' => $value::class, 'id' => $value->getKey()];
            } elseif (is_scalar($value) || $value === null || is_array($value)) {
                $payload[$name] = is_array($value) ? array_map(
                    fn ($v) => is_scalar($v) || $v === null ? $v : (string) $v,
                    $value
                ) : $value;
            }
        }

        // Convert PascalCase event class to "snake_case.dot" name.
        $eventName = strtolower(preg_replace('/(?<!^)([A-Z])/', '.$1', class_basename($event)));

        SystemEvent::create([
            'event_name'     => $eventName,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'actor_id'       => Auth::id(),
            'payload'        => $payload,
            'occurred_at'    => now(),
        ]);
    }
}
