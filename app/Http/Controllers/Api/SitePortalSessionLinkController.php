<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SitePortalClientRegistry;
use App\Services\SitePortalNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SitePortalSessionLinkController extends Controller
{
    public function __invoke(Request $request, SitePortalNavigation $navigation, SitePortalClientRegistry $clients): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'item' => ['required', 'string'],
            'timestamp' => ['required', 'integer'],
            'signature' => ['required', 'string', 'size:64'],
        ]);

        $client = $clients->find($data['client_id']);
        $email = strtolower($data['email']);

        abort_unless(
            is_array($client)
            && abs(time() - (int) $data['timestamp']) <= 60
            && hash_equals(
                hash_hmac('sha256', implode('|', [$data['client_id'], $data['subject'], $email, $data['item'], $data['timestamp']]), $client['secret']),
                $data['signature'],
            ),
            Response::HTTP_UNAUTHORIZED,
        );

        $organization = $client['organization'];
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        abort_unless($user instanceof User && $user->isActive() && $user->canAccessTenant($organization), Response::HTTP_UNAUTHORIZED);

        $item = collect($navigation->for($user, $organization, [$data['item']]))->first();
        abort_unless(is_array($item) && is_string($item['url'] ?? null), Response::HTTP_FORBIDDEN);

        $ticket = Str::random(96);
        Cache::put('site-portal-session:'.$ticket, [
            'user_id' => $user->getKey(),
            'organization_slug' => $organization->slug,
            'url' => $item['url'],
        ], now()->addMinute());

        return response()->json(['entry_url' => route('site-portal.session', ['ticket' => $ticket])]);
    }
}
