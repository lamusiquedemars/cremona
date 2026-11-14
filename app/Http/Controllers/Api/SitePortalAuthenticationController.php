<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SitePortalClientRegistry;
use App\Services\SitePortalNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SitePortalAuthenticationController extends Controller
{
    public function __invoke(Request $request, SitePortalNavigation $navigation, SitePortalClientRegistry $clients): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string'],
            'email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'timestamp' => ['required', 'integer'],
            'signature' => ['required', 'string', 'size:64'],
        ]);

        $client = $clients->find($data['client_id']);
        $email = strtolower($data['email']);

        abort_unless(
            is_array($client)
            && abs(time() - (int) $data['timestamp']) <= 60
            && hash_equals(
                hash_hmac('sha256', $data['client_id'].'|'.$data['subject'].'|'.$email.'|'.$data['timestamp'], $client['secret']),
                $data['signature'],
            ),
            Response::HTTP_UNAUTHORIZED,
        );

        $organization = $client['organization'];

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        abort_unless(
            $user instanceof User
            && $user->isActive()
            && $user->canAccessTenant($organization),
            Response::HTTP_UNAUTHORIZED,
        );

        return response()->json([
            'subject' => (string) $user->getAuthIdentifier(),
            'name' => $user->name,
            'email' => $user->email,
            'organization' => ['slug' => $organization->slug, 'name' => $organization->name],
            'navigation' => $navigation->for($user, $organization, $client['items']),
        ]);
    }
}
