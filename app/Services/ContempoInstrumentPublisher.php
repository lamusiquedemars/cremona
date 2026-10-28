<?php

namespace App\Services;

use App\Models\InstrumentAsset;
use App\Models\OrganizationIntegration;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class ContempoInstrumentPublisher
{
    public function publish(InstrumentAsset $instrument): void
    {
        $connection = OrganizationIntegration::query()->where('provider', 'contempo_cms')->where('name', 'instrument_projection')->first();

        try {
            $credentials = $connection?->credentials ?? [];
        } catch (DecryptException) {
            throw new LogicException('Le connecteur de publication doit être reconfiguré avant de pouvoir synchroniser le site.');
        }

        $endpoint = $credentials['endpoint'] ?? null;
        $token = $credentials['token'] ?? null;
        if (blank($endpoint) || blank($token)) {
            throw new LogicException('Aucun connecteur de publication n’est configuré pour cette organisation.');
        }
        $response = Http::acceptJson()->asJson()->withToken($token)->timeout(15)->post($endpoint, [
            'id' => $instrument->id, 'reference' => $instrument->reference, 'slug' => $instrument->public_slug, 'name' => $instrument->public_title ?: $instrument->name, 'family' => $instrument->family, 'maker' => $instrument->maker, 'description' => $instrument->public_description ?: $instrument->description, 'price_label' => $instrument->public_price_label, 'attributes' => $instrument->attributes, 'media' => $this->publicMedia($instrument), 'sale_amount' => $instrument->available_for_sale ? $instrument->suggested_sale_amount : null, 'rental_amount' => $instrument->available_for_rental ? $instrument->rentalMonthlyAmount() : null, 'availability' => $instrument->is_site_published ? $instrument->status->value : 'archived', 'published_at' => now()->toIso8601String(),
        ]);
        if (! $response->successful()) {
            throw new LogicException("Le site a refusé la publication (HTTP {$response->status()}).");
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function publicMedia(InstrumentAsset $instrument): array
    {
        $media = $instrument->media;

        // Older edit submissions can leave the JSON payload double-encoded.
        // Accept it while the form continues to present the same media list.
        if (is_string($media)) {
            $media = json_decode($media, true) ?: [];
        }

        return collect($media)
            ->filter(fn (array $media): bool => (bool) ($media['is_public'] ?? false))
            ->map(function (array $media): ?array {
                $source = $media['path'] ?? $media['url'] ?? null;

                if (blank($source)) {
                    return null;
                }

                $media['url'] = Str::startsWith($source, ['http://', 'https://'])
                    ? $source
                    : Storage::disk('public')->url($source);
                unset($media['path']);

                return $media;
            })
            ->filter()
            ->values()
            ->all();
    }
}
