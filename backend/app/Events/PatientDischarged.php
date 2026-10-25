<?php

namespace App\Events;

use App\Models\Admission\Admission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientDischarged
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Admission $admission)
    {
    }
}
