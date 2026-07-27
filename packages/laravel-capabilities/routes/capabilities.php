<?php

/**
 * Capability HTTP routes (scaffold).
 * Single catalog + invoke API — product CLI is a client (D-009).
 *
 * Registered only when surfaces.http.enabled is true.
 */

use Illuminate\Support\Facades\Route;

// Route::prefix(config('capabilities.surfaces.http.prefix', 'capabilities'))
//     ->middleware(config('capabilities.surfaces.http.middleware', ['api']))
//     ->group(function () {
//         // GET  /capabilities
//         // GET  /capabilities/{name}
//         // POST /capabilities/{name}
//         // approvals + device-code auth helpers
//     });
