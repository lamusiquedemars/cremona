<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\OrganizationInvoiceSettings;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SuperPdpOAuthController extends Controller
{
    public function authorize(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->hasOrganizationPermission(OrganizationPermission::ManageIntegrations, $organization), Response::HTTP_FORBIDDEN);
        abort_unless(filled(config('services.super_pdp.sandbox_client_id')) && filled(config('services.super_pdp.sandbox_client_secret')), Response::HTTP_UNPROCESSABLE_ENTITY, 'Le client OAuth Super PDP sandbox n’est pas configuré.');

        $state = Str::random(64);
        $request->session()->put('super_pdp_oauth', ['state' => $state, 'organization_id' => $organization->id, 'user_id' => $request->user()->id]);

        return redirect()->away(rtrim((string) config('services.super_pdp.api_url'), '/').'/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.super_pdp.sandbox_client_id'),
            'redirect_uri' => config('services.super_pdp.sandbox_redirect_uri'),
            'response_type' => 'code',
            'state' => $state,
        ]));
    }

    public function callback(Request $request, OrganizationIntegrationManager $manager, OrganizationContext $context): RedirectResponse
    {
        $pending = $request->session()->pull('super_pdp_oauth');
        abort_unless(is_array($pending) && $request->user()?->id === $pending['user_id'] && is_string($request->state) && hash_equals($pending['state'], $request->state), Response::HTTP_FORBIDDEN);
        $organization = Organization::findOrFail($pending['organization_id']);
        abort_unless($request->user()->hasOrganizationPermission(OrganizationPermission::ManageIntegrations, $organization), Response::HTTP_FORBIDDEN);
        if ($request->filled('error')) return redirect('/dashboard/'.$organization->slug.'/organization-details')->with('error', 'L’autorisation Super PDP a été annulée.');

        try {
            $token = Http::asForm()->post(rtrim((string) config('services.super_pdp.api_url'), '/').'/oauth2/token', [
                'code' => $request->string('code')->toString(), 'client_id' => config('services.super_pdp.sandbox_client_id'), 'client_secret' => config('services.super_pdp.sandbox_client_secret'), 'redirect_uri' => config('services.super_pdp.sandbox_redirect_uri'), 'grant_type' => 'authorization_code',
            ])->throw()->json();
            $accessToken = $token['access_token'] ?? null;
            abort_unless(is_string($accessToken) && filled($accessToken), Response::HTTP_UNPROCESSABLE_ENTITY);
            $session = Http::acceptJson()->withToken($accessToken)->get(rtrim((string) config('services.super_pdp.api_url'), '/').'/v1.beta/oauth2_sessions/me')->throw()->json();
        } catch (\Throwable $exception) {
            report($exception);
            return redirect('/dashboard/'.$organization->slug.'/organization-details')->with('error', 'Super PDP a refusé la connexion sandbox. Vérifiez le client OAuth et l’URL de redirection.');
        }

        $verification = (string) ($session['company_verification_status'] ?? 'pending');
        $context->run($organization, function () use ($manager, $token, $session, $verification, $request, $organization): void {
            $manager->configure('super_pdp', 'sandbox', ['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? null, 'expires_in' => $token['expires_in'] ?? null, 'company_verification_status' => $verification, 'session_company_id' => $session['company_id'] ?? null], $request->user());
            OrganizationInvoiceSettings::query()->updateOrCreate(['organization_id' => $organization->id], ['super_pdp_enabled' => true, 'super_pdp_connection_status' => $verification]);
        });

        return redirect('/dashboard/'.$organization->slug.'/organization-details')->with('success', 'Super PDP sandbox est connecté. Statut de vérification : '.$verification.'.');
    }
}
