<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;

class GoogleAdsApiClient
{
    private const API_VERSION = 'v25';

    private ?string $accessToken = null;

    /** @param array<string, mixed> $credentials */
    public function __construct(array $credentials)
    {
        $this->credentials = app(GoogleAdsCredentials::class)->resolve($credentials);
    }

    /** @var array<string, mixed> */
    private readonly array $credentials;

    /** @param array<int, array<string, mixed>> $operations */
    public function mutate(string $resource, array $operations): array
    {
        return $this->request()->post($this->endpoint("{$resource}:mutate"), ['operations' => $operations])->throw()->json();
    }

    public function searchStream(string $query): array
    {
        return $this->request()->post($this->endpoint('googleAds:searchStream'), ['query' => $query])->throw()->json();
    }

    private function endpoint(string $resource): string
    {
        $customerId = preg_replace('/\D/', '', (string) $this->credentials['customer_id']);

        return 'https://googleads.googleapis.com/'.self::API_VERSION."/customers/{$customerId}/{$resource}";
    }

    private function request(): PendingRequest
    {
        foreach (['customer_id', 'developer_token', 'oauth_client_id', 'oauth_client_secret', 'refresh_token'] as $key) {
            if (blank($this->credentials[$key] ?? null)) {
                throw new LogicException('Google Ads n’est pas encore entièrement configuré.');
            }
        }

        $token = $this->accessToken;
        if ($token === null) {
            $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $this->credentials['oauth_client_id'],
                'client_secret' => $this->credentials['oauth_client_secret'],
                'refresh_token' => $this->credentials['refresh_token'],
            ])->throw()->json('access_token');
        }

        if (! is_string($token) || $token === '') {
            throw new LogicException('Google n’a pas renvoyé de jeton d’accès utilisable.');
        }

        $this->accessToken = $token;

        $request = Http::acceptJson()->withToken($token)->withHeader('developer-token', $this->credentials['developer_token'])->timeout(30);

        return filled($this->credentials['login_customer_id'] ?? null)
            ? $request->withHeader('login-customer-id', preg_replace('/\D/', '', $this->credentials['login_customer_id']))
            : $request;
    }
}
