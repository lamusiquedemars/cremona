<?php

use App\Http\Controllers\Api\BrevoMeetingWebhookController;
use App\Http\Controllers\Api\IncomingRequestController;
use App\Http\Middleware\AuthenticateOrganizationIntegration;
use Illuminate\Support\Facades\Route;

Route::post('/v1/incoming-requests', [IncomingRequestController::class, 'store'])
    ->middleware([
        'throttle:60,1',
        AuthenticateOrganizationIntegration::class.':maracuja_cms',
    ])
    ->name('api.v1.incoming-requests.store');

Route::post('/v1/integrations/brevo/meetings/{event}', BrevoMeetingWebhookController::class)
    ->whereIn('event', ['booked', 'started', 'cancelled'])
    ->middleware([
        'throttle:120,1',
        AuthenticateOrganizationIntegration::class.':brevo',
    ])
    ->name('api.v1.integrations.brevo.meetings.store');
