<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class SitePortalSessionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $ticket = $request->string('ticket')->toString();
        $session = Cache::pull('site-portal-session:'.$ticket);

        abort_unless(is_array($session) && is_numeric($session['user_id'] ?? null) && is_string($session['organization_slug'] ?? null) && is_string($session['url'] ?? null), Response::HTTP_UNAUTHORIZED);

        $user = User::find($session['user_id']);
        $organization = Organization::query()->where('slug', $session['organization_slug'])->where('status', 'active')->first();
        abort_unless($user instanceof User && $user->isActive() && $organization instanceof Organization && $user->canAccessTenant($organization), Response::HTTP_UNAUTHORIZED);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->away($session['url']);
    }
}
