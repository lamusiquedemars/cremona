<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
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

    /** @param array<string, mixed> $invoice */
    public function convertEn16931ToCii(string $clientId, string $clientSecret, array $invoice): string
    {
        return $this->authenticated($clientId, $clientSecret)
            ->accept('application/xml')
            ->post($this->endpoint('/v1.beta/invoices/convert?from=en16931&to=cii'), $invoice)
            ->throw()
            ->body();
    }

    /** @return array<string, mixed> */
    public function validateCii(string $clientId, string $clientSecret, string $content, string $filename): array
    {
        return $this->authenticated($clientId, $clientSecret)
            ->attach('file_name', $content, $filename)
            ->post($this->endpoint('/v1.beta/validation_reports'))
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    public function submitCii(string $clientId, string $clientSecret, string $content, string $externalId, string $processingRule): array
    {
        return $this->authenticated($clientId, $clientSecret)
            ->withBody($content, 'application/xml')
            ->post($this->endpoint('/v1.beta/invoices?external_id='.urlencode($externalId).'&processing_rule='.urlencode($processingRule)))
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    public function invoiceEvents(string $clientId, string $clientSecret, string $invoiceId): array
    {
        return $this->authenticated($clientId, $clientSecret)
            ->acceptJson()
            ->get($this->endpoint('/v1.beta/invoice_events'), ['invoice_id' => $invoiceId])
            ->throw()
            ->json();
    }

    private function authenticated(string $clientId, string $clientSecret): PendingRequest
    {
        try {
            $token = Http::asForm()->acceptJson()->timeout(15)->post($this->endpoint('/oauth2/token'), [
                'grant_type' => 'client_credentials', 'client_id' => $clientId, 'client_secret' => $clientSecret,
            ])->throw()->json('access_token');
        } catch (ConnectionException $exception) {
            throw new LogicException('Super PDP est temporairement inaccessible. Réessayez plus tard.', previous: $exception);
        }
        if (! is_string($token) || $token === '') {
            throw new LogicException('Super PDP n’a pas retourné de jeton serveur exploitable.');
        }

        return Http::timeout(30)->withToken($token);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.super_pdp.api_url'), '/').$path;
    }
}
