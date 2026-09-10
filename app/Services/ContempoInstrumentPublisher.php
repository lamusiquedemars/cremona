<?php

namespace App\Services;

use App\Models\InstrumentAsset;
use App\Models\OrganizationIntegration;
use Illuminate\Support\Facades\Http;
use LogicException;

class ContempoInstrumentPublisher
{
    public function publish(InstrumentAsset $instrument): void
    {
        $connection = OrganizationIntegration::query()->where('provider', 'contempo_cms')->where('name', 'instrument_projection')->first();
        $endpoint = $connection?->credentials['endpoint'] ?? null;
        $token = $connection?->credentials['token'] ?? null;
        if (blank($endpoint) || blank($token)) {
            throw new LogicException('La projection Contempo n’est pas configurée pour cette organisation.');
        }
        $response = Http::acceptJson()->asJson()->withToken($token)->timeout(15)->post($endpoint, [
            'id' => $instrument->id, 'reference' => $instrument->reference, 'slug' => $instrument->public_slug, 'name' => $instrument->public_title ?: $instrument->name, 'family' => $instrument->family, 'maker' => $instrument->maker, 'description' => $instrument->public_description ?: $instrument->description, 'price_label' => $instrument->public_price_label, 'sale_amount' => $instrument->available_for_sale ? $instrument->suggested_sale_amount : null, 'rental_amount' => $instrument->available_for_rental ? $instrument->suggested_rental_amount : null, 'availability' => $instrument->status->value, 'published_at' => now()->toIso8601String(),
        ]);
        if (! $response->successful()) {
            throw new LogicException("Contempo a refusé la publication (HTTP {$response->status()}).");
        }
    }
}
