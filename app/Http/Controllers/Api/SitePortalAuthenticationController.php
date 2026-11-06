<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\SitePortalNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

final class SitePortalAuthenticationController extends Controller
{
    public function __invoke(Request $request, SitePortalNavigation $navigation): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'timestamp' => ['required', 'integer'],
            'signature' => ['required', 'string', 'size:64'],
        ]);

        $client = config('cremona.site_portal.clients.'.$data['client_id']);
        $email = strtolower($data['email']);

        abort_unless(
            is_array($client)
            && is_string($client['logout_secret'] ?? null)
            && abs(time() - (int) $data['timestamp']) <= 60
            && hash_equals(
                hash_hmac('sha256', $data['client_id'].'|'.$email.'|'.$data['timestamp'], $client['logout_secret']),
                $data['signature'],
            ),
            Response::HTTP_UNAUTHORIZED,
        );

        $organization = Organization::query()
            ->where('slug', $client['organization'] ?? null)
            ->where('status', 'active')
            ->firstOrFail();

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        abort_unless(
            $user instanceof User
            && $user->isActive()
            && Hash::check($data['password'], $user->password)
            && $user->canAccessTenant($organization),
            Response::HTTP_UNAUTHORIZED,
        );

        return response()->json([
            'subject' => (string) $user->getAuthIdentifier(),
            'name' => $user->name,
            'email' => $user->email,
            'organization' => ['slug' => $organization->slug, 'name' => $organization->name],
            'navigation' => $navigation->for($user, $organization, $client['items'] ?? []),
        ]);
    }
}
