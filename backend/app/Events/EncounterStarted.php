<?php

namespace App\Events;

use App\Models\Clinical\Encounter;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EncounterStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Encounter $encounter)
    {
    }
}
