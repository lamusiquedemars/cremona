<?php

use App\Http\Controllers\GoogleAdsOAuthController;
use App\Http\Controllers\GoogleAdsAgencyOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/platform/organizations');
});

Route::middleware('auth')->get('/dashboard', function () {
    return redirect('/platform/organizations');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/integrations/google-ads/agency/authorize', [GoogleAdsAgencyOAuthController::class, 'authorize'])
        ->name('google-ads.agency.authorize');
    Route::get('/integrations/google-ads/agency/callback', [GoogleAdsAgencyOAuthController::class, 'callback'])
        ->name('google-ads.agency.callback');
    Route::post('/integrations/google-ads/agency/enable', [GoogleAdsAgencyOAuthController::class, 'enable'])
        ->name('google-ads.agency.enable');
    Route::post('/integrations/google-ads/agency/disable', [GoogleAdsAgencyOAuthController::class, 'disable'])
        ->name('google-ads.agency.disable');
    Route::get('/integrations/google-ads/{integration}/authorize', [GoogleAdsOAuthController::class, 'authorize'])
        ->name('google-ads.oauth.authorize');
    Route::get('/integrations/google-ads/callback', [GoogleAdsOAuthController::class, 'callback'])
        ->name('google-ads.oauth.callback');
});
