<?php

use App\Http\Controllers\Api\AcquisitionSummaryController;
use App\Http\Controllers\Api\BrevoMeetingWebhookController;
use App\Http\Controllers\Api\IncomingRequestController;
use App\Http\Controllers\Api\SitePortalProfileController;
use App\Http\Controllers\Api\SitePortalAuthenticationController;
use App\Http\Controllers\Api\SitePortalSessionLinkController;
use App\Http\Middleware\AuthenticateOrganizationIntegration;
use Illuminate\Support\Facades\Route;

Route::post('/v1/incoming-requests', [IncomingRequestController::class, 'store'])
    ->middleware([
        'throttle:60,1',
        AuthenticateOrganizationIntegration::class.':maracuja_cms',
    ])
    ->name('api.v1.incoming-requests.store');

Route::get('/v1/acquisition/summary', AcquisitionSummaryController::class)
    ->middleware([
        'throttle:60,1',
        AuthenticateOrganizationIntegration::class.':maracuja_cms',
    ])
    ->name('api.v1.acquisition.summary');

Route::post('/v1/integrations/brevo/meetings/{event}', BrevoMeetingWebhookController::class)
    ->whereIn('event', ['booked', 'started', 'cancelled'])
    ->middleware([
        'throttle:120,1',
        AuthenticateOrganizationIntegration::class.':brevo',
    ])
    ->name('api.v1.integrations.brevo.meetings.store');

Route::post('/v1/site-portal/authenticate', SitePortalAuthenticationController::class)
    ->middleware('throttle:10,1')
    ->name('api.v1.site-portal.authenticate');

Route::post('/v1/site-portal/session-link', SitePortalSessionLinkController::class)
    ->middleware('throttle:10,1')
    ->name('api.v1.site-portal.session-link');

Route::get('/v1/site-portal/profile', SitePortalProfileController::class)
    ->middleware(['auth:api', 'throttle:30,1'])
    ->name('api.v1.site-portal.profile');
