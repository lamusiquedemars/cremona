<?php

namespace App\Http\Controllers;

use App\Services\GoogleAdsAgencyAuthorization;
use App\Services\GoogleAdsCredentials;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GoogleAdsAgencyOAuthController extends Controller
{
    public function authorize(Request $request, GoogleAdsCredentials $credentials): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, Response::HTTP_FORBIDDEN);
        abort_unless($credentials->centralOAuthIsConfigured(), Response::HTTP_UNPROCESSABLE_ENTITY);

        $state = Str::random(64);
        $request->session()->put('google_ads_agency_oauth', [
            'state' => $state,
            'user_id' => $request->user()->getKey(),
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_ads.oauth_client_id'),
            'redirect_uri' => route('google-ads.agency.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/adwords',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]));
    }

    public function callback(Request $request, GoogleAdsAgencyAuthorization $authorization): RedirectResponse
    {
        $pending = $request->session()->pull('google_ads_agency_oauth');
        abort_unless($request->user()?->is_platform_admin && is_array($pending) && $request->user()->getKey() === $pending['user_id'], Response::HTTP_FORBIDDEN);
        abort_unless(is_string($request->state) && hash_equals($pending['state'], $request->state), Response::HTTP_FORBIDDEN);

        if ($request->filled('error')) {
            return redirect()->route('filament.platform.pages.google-ads-infrastructure')->with('error', 'L’autorisation Google de l’agence a été annulée.');
        }

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->string('code')->toString(),
                'client_id' => config('services.google_ads.oauth_client_id'),
                'client_secret' => config('services.google_ads.oauth_client_secret'),
                'redirect_uri' => route('google-ads.agency.callback'),
                'grant_type' => 'authorization_code',
            ])->throw();
        } catch (RequestException $exception) {
            report($exception);

            return redirect()->route('filament.platform.pages.google-ads-infrastructure')->with('error', 'Google a refusé l’autorisation de l’agence. Vérifie le client OAuth et l’URI de redirection.');
        }

        $refreshToken = $response->json('refresh_token');
        abort_unless(is_string($refreshToken) && filled($refreshToken), Response::HTTP_UNPROCESSABLE_ENTITY);
        $authorization->store($refreshToken);

        return redirect()->route('filament.platform.pages.google-ads-infrastructure')->with('success', 'Autorisation d’agence enregistrée dans le coffre chiffré. La bascule reste à confirmer.');
    }

    public function enable(Request $request, GoogleAdsCredentials $credentials, GoogleAdsAgencyAuthorization $authorization): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, Response::HTTP_FORBIDDEN);
        abort_unless($credentials->centralInfrastructureIsReady(), Response::HTTP_UNPROCESSABLE_ENTITY);

        $authorization->enableCentralInfrastructure();

        return redirect()->route('filament.platform.pages.google-ads-infrastructure')->with('success', 'Infrastructure Google Ads centralisée activée. Les organisations conservent seulement leur compte client.');
    }

    public function disable(Request $request, GoogleAdsAgencyAuthorization $authorization): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, Response::HTTP_FORBIDDEN);
        $authorization->disableCentralInfrastructure();

        return redirect()->route('filament.platform.pages.google-ads-infrastructure')->with('success', 'Retour au mode historique effectué. Aucune donnée d’organisation n’a été modifiée.');
    }
}
