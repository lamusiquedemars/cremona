<?php

use App\Http\Controllers\Api\IncomingRequestController;
use App\Http\Middleware\AuthenticateOrganizationIntegration;
use Illuminate\Support\Facades\Route;

Route::post('/v1/incoming-requests', [IncomingRequestController::class, 'store'])
    ->middleware([
        'throttle:60,1',
        AuthenticateOrganizationIntegration::class,
    ])
    ->name('api.v1.incoming-requests.store');
