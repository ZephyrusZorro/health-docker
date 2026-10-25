<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

/**
 * Base for versioned (v1) API controllers. The parent {@see Controller}
 * provides ok()/created()/fail() helpers that wrap responses in our
 * standard {success, message, data} envelope — used across every endpoint.
 */
abstract class ApiController extends Controller
{
    //
}
