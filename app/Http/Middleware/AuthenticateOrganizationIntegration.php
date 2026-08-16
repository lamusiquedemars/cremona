<?php

namespace App\Http\Middleware;

use App\Models\OrganizationIntegration;
use App\Tenancy\OrganizationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticateOrganizationIntegration
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next, ?string $provider = null): mixed
    {
        $parts = explode('.', (string) $request->bearerToken(), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return $this->unauthorized();
        }

        [$keyId, $secret] = $parts;
        $integration = OrganizationIntegration::query()
            ->withoutGlobalScopes()
            ->where('key_id', $keyId)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();

        if ($integration === null
            || ($provider !== null && $integration->provider !== $provider)
            || $integration->token_hash === null
            || ! hash_equals($integration->token_hash, hash('sha256', $secret))) {
            return $this->unauthorized();
        }

        $organization = $integration->organization()->where('status', 'active')->first();

        if ($organization === null) {
            return $this->unauthorized();
        }

        $request->attributes->set('organization_integration', $integration);
        $this->context->set($organization);

        try {
            return $next($request);
        } finally {
            $this->context->forget();
        }
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'message' => 'Invalid integration credentials.',
        ], 401);
    }
}
