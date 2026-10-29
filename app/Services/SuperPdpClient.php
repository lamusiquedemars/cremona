<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use LogicException;

class SuperPdpClient
{
    /** @return array{company_id: ?string, verification_status: string, checked_at: string} */
    public function checkConnection(string $clientId, string $clientSecret): array
    {
        if (blank($clientId) || blank($clientSecret)) {
            throw new LogicException('Les identifiants serveur Super PDP sont incomplets.');
        }

        try {
            $token = Http::asForm()->acceptJson()->timeout(15)->post($this->endpoint('/oauth2/token'), [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ])->throw()->json();
            $accessToken = $token['access_token'] ?? null;
            if (! is_string($accessToken) || $accessToken === '') {
                throw new LogicException('Super PDP n’a pas retourné de jeton serveur exploitable.');
            }

            $session = Http::acceptJson()->timeout(15)->withToken($accessToken)
                ->get($this->endpoint('/v1.beta/oauth2_sessions/me'))
                ->throw()->json();
        } catch (ConnectionException $exception) {
            throw new LogicException('Super PDP est temporairement inaccessible. Réessayez plus tard.', previous: $exception);
        }

        return [
            'company_id' => is_scalar($session['company_id'] ?? null) ? (string) $session['company_id'] : null,
            'verification_status' => is_scalar($session['company_verification_status'] ?? null) ? (string) $session['company_verification_status'] : 'unknown',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.super_pdp.api_url'), '/').$path;
    }
}
