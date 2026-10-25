<?php

use Illuminate\Support\Facades\Route;

/*
 * The HMS is an API-first product (SRS §6.2). The web route file exists
 * purely to satisfy the framework and host a health-check page.
 * The frontend is a separate Next.js application served on port 3000.
 */

Route::get('/', fn () => response()->json([
    'name'    => config('app.name'),
    'version' => 'v1',
    'status'  => 'ok',
    'docs'    => url('/api/documentation'),
]));

Route::get('/up', fn () => response()->json(['status' => 'healthy', 'time' => now()]));
