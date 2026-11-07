<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SitePortalClientRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Completes the browser-mediated logout started by a connected site.
 *
 * OAuth 2.0 does not define a universal logout endpoint. We therefore keep
 * the contract deliberately narrow: a configured public client, its exact
 * HMAC proof, a short state issued by the site, and a fixed return URL.
 */
final class SitePortalLogoutController extends Controller
{
    public function __invoke(Request $request, SitePortalClientRegistry $clients): RedirectResponse
    {
        $clientId = $request->string('client_id')->toString();
        $state = $request->string('state')->toString();
        $portal = $clients->find($clientId);

        abort_unless(
            filled($clientId)
            && strlen($state) === 96
            && is_array($portal)
            && is_string($portal['secret'] ?? null)
            && filled($portal['secret'])
            && hash_equals(hash_hmac('sha256', $clientId.'|'.$state, $portal['secret']), $request->string('signature')->toString())
            && is_string($portal['logout_redirect_uri'] ?? null),
            Response::HTTP_FORBIDDEN,
        );

        $user = Auth::user();

        if ($user instanceof User) {
            $organization = $portal['organization'];

            // Never let a site sign out a different central account on a
            // shared browser. The site can still close its own session; the
            // central session remains intact when it is not the same space.
            if ($user->canAccessTenant($organization)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } else {
                Log::notice('Déconnexion fédérée limitée à la session du site.', [
                    'reason' => 'central_user_has_no_portal_access',
                    'client_id' => $clientId,
                ]);
            }
        }

        return redirect()
            ->away($this->completionUrl($portal['logout_redirect_uri'], $state))
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function completionUrl(string $url, string $state): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
