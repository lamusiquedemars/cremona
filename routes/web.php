<?php

use App\Http\Controllers\GoogleAdsAgencyOAuthController;
use App\Http\Controllers\GoogleAdsOAuthController;
use App\Http\Controllers\PrivateDocumentDownloadController;
use App\Models\Organization;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/manifest.webmanifest', function () {
    return response()
        ->view('pwa.manifest')
        ->header('Content-Type', 'application/manifest+json')
        ->header('Cache-Control', 'public, max-age=3600');
})->name('pwa.manifest');

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard/logout', function (Request $request) {
    if ($request->user() === null) {
        return redirect('/dashboard/login');
    }

    return view('auth.logout-confirmation', [
        'logoutAction' => route('filament.admin.auth.logout'),
        'cancelUrl' => url('/dashboard'),
    ]);
})->name('logout.confirmation.admin');

Route::get('/platform/logout', function (Request $request) {
    if ($request->user() === null) {
        return redirect('/platform/login');
    }

    return view('auth.logout-confirmation', [
        'logoutAction' => route('filament.platform.auth.logout'),
        'cancelUrl' => url('/platform'),
    ]);
})->name('logout.confirmation.platform');

Route::middleware('auth')->get('/dashboard', function (Request $request) {
    $user = $request->user();

    if ($user->is_platform_admin) {
        return redirect('/platform/organizations');
    }

    $organization = $user->getDefaultTenant(Filament::getPanel('admin'));

    abort_unless($organization instanceof Organization, 403, 'Votre compte n’est associé à aucune organisation active.');

    return redirect(Filament::getPanel('admin')->getUrl($organization));
});

Route::middleware('auth')->group(function (): void {
    Route::get('/documents/{publicId}/download', PrivateDocumentDownloadController::class)
        ->whereUlid('publicId')
        ->name('private-documents.download');
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
