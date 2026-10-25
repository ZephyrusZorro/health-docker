<?php

namespace App\Events;

use App\Models\Identity\Patient;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Patient $patient)
    {
    }
}
