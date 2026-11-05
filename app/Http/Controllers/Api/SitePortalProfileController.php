<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\SitePortalNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SitePortalProfileController extends Controller
{
    public function __invoke(Request $request, SitePortalNavigation $navigation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        abort_unless($user->tokenCan('site:access'), 403);

        $clientId = $user->token()?->client_id;
        $client = is_string($clientId) ? config("cremona.site_portal.clients.{$clientId}") : null;

        abort_unless(is_array($client), 403);

        $organization = Organization::query()
            ->where('slug', $client['organization'] ?? null)
            ->where('status', 'active')
            ->firstOrFail();

        abort_unless($user->canAccessTenant($organization), 403);

        return response()->json([
            'subject' => (string) $user->getAuthIdentifier(),
            'name' => $user->name,
            'email' => $user->email,
            'organization' => [
                'slug' => $organization->slug,
                'name' => $organization->name,
            ],
            'navigation' => $navigation->for($user, $organization, $client['items'] ?? []),
            'site' => [
                'name' => $client['site_name'] ?? $organization->name,
                'url' => $client['site_url'] ?? null,
            ],
        ]);
    }
}
