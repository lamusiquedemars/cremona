<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BrevoClient
{
    public function account(string $apiKey): array
    {
        return $this->request($apiKey)
            ->get('/v3/account')
            ->throw()
            ->json();
    }

    private function request(string $apiKey): PendingRequest
    {
        return Http::baseUrl('https://api.brevo.com')
            ->acceptJson()
            ->withHeader('api-key', $apiKey)
            ->timeout(10)
            ->retry(2, 200);
    }
}
