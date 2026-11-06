<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Completes the browser-mediated logout started by a connected site.
 *
 * OAuth 2.0 does not define a universal logout endpoint. We therefore keep
 * the contract deliberately narrow: a configured public client, its exact
 * browser Origin, a short state issued by the site, and a fixed return URL.
 */
final class SitePortalLogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $clientId = $request->string('client_id')->toString();
        $state = $request->string('state')->toString();
        $portal = config("cremona.site_portal.clients.{$clientId}");

        abort_unless(
            filled($clientId)
            && strlen($state) === 96
            && is_array($portal)
            && $this->hasExpectedOrigin($request, $portal)
            && is_string($portal['logout_redirect_uri'] ?? null),
            Response::HTTP_FORBIDDEN,
        );

        $user = Auth::user();

        if ($user instanceof User) {
            $organization = Organization::query()
                ->where('slug', $portal['organization'] ?? null)
                ->where('status', 'active')
                ->firstOrFail();

            // Never let a site sign out a different central account on a
            // shared browser: the active central user must own this portal.
            abort_unless($user->canAccessTenant($organization), Response::HTTP_FORBIDDEN);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->away($this->completionUrl($portal['logout_redirect_uri'], $state))
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** @param array<string, mixed> $portal */
    private function hasExpectedOrigin(Request $request, array $portal): bool
    {
        $origin = rtrim((string) $request->headers->get('Origin'), '/');
        $siteUrl = $portal['site_url'] ?? null;

        if ($origin === '' || ! is_string($siteUrl)) {
            return false;
        }

        $parts = parse_url($siteUrl);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $expected = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $expected .= ':'.$parts['port'];
        }

        return hash_equals($expected, $origin);
    }

    private function completionUrl(string $url, string $state): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
