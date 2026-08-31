<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationPermission;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsCredentials;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GoogleAdsOAuthController extends Controller
{
    public function authorize(Request $request, int $integration, GoogleAdsCredentials $googleAdsCredentials): RedirectResponse
    {
        $integration = OrganizationIntegration::withoutGlobalScopes()->findOrFail($integration);
        abort_unless($request->user()?->hasOrganizationPermission(OrganizationPermission::ManageIntegrations, $integration->organization), Response::HTTP_FORBIDDEN);

        $credentials = $googleAdsCredentials->resolve($integration->credentials);
        abort_unless(filled($credentials['oauth_client_id'] ?? null) && filled($credentials['oauth_client_secret'] ?? null), Response::HTTP_UNPROCESSABLE_ENTITY);

        $state = Str::random(64);
        $request->session()->put('google_ads_oauth', [
            'state' => $state,
            'integration_id' => $integration->getKey(),
            'user_id' => $request->user()->getKey(),
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $credentials['oauth_client_id'],
            'redirect_uri' => route('google-ads.oauth.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/adwords',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]));
    }

    public function callback(Request $request, OrganizationIntegrationManager $manager, OrganizationContext $context, GoogleAdsCredentials $googleAdsCredentials): RedirectResponse
    {
        $pending = $request->session()->pull('google_ads_oauth');
        abort_unless(is_array($pending) && $request->user()?->getKey() === $pending['user_id'], Response::HTTP_FORBIDDEN);
        abort_unless(is_string($request->state) && hash_equals($pending['state'], $request->state), Response::HTTP_FORBIDDEN);

        $integration = OrganizationIntegration::withoutGlobalScopes()->findOrFail($pending['integration_id']);
        abort_unless($request->user()->hasOrganizationPermission(OrganizationPermission::ManageIntegrations, $integration->organization), Response::HTTP_FORBIDDEN);

        if ($request->filled('error')) {
            return redirect('/dashboard/'.$integration->organization->slug)->with('error', 'L’autorisation Google a été annulée.');
        }

        $organizationCredentials = $integration->credentials;
        $credentials = $googleAdsCredentials->resolve($organizationCredentials);
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $request->string('code')->toString(),
            'client_id' => $credentials['oauth_client_id'],
            'client_secret' => $credentials['oauth_client_secret'],
            'redirect_uri' => route('google-ads.oauth.callback'),
            'grant_type' => 'authorization_code',
        ])->throw();

        $refreshToken = $response->json('refresh_token');
        abort_unless(is_string($refreshToken) && filled($refreshToken), Response::HTTP_UNPROCESSABLE_ENTITY);

        $context->run($integration->organization, fn () => $manager->configure(
            provider: 'google_ads', name: 'reporting',
            credentials: [...$organizationCredentials, 'refresh_token' => $refreshToken], actor: $request->user(),
        ));

        return redirect('/dashboard/'.$integration->organization->slug)->with('success', 'Google Ads est autorisé.');
    }
}
