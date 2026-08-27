<?php

use App\Http\Controllers\GoogleAdsOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/platform/organizations');
});

Route::middleware('auth')->get('/dashboard', function () {
    return redirect('/platform/organizations');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/integrations/google-ads/{integration}/authorize', [GoogleAdsOAuthController::class, 'authorize'])
        ->name('google-ads.oauth.authorize');
    Route::get('/integrations/google-ads/callback', [GoogleAdsOAuthController::class, 'callback'])
        ->name('google-ads.oauth.callback');
});
