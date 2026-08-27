<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class GoogleAdsCampaignDraft
{
    /** @return array<string, mixed> */
    public function preview(Campaign $campaign): array
    {
        if ($campaign->channel !== 'google_ads') {
            throw ValidationException::withMessages(['channel' => 'Seules les campagnes Google Ads peuvent être publiées dans Google Ads.']);
        }

        $configuration = $campaign->configuration ?? [];
        $this->requireValue($configuration, 'conversion_goal');
        $this->requireValue($configuration, 'final_url');
        $this->requireValue($configuration, 'daily_budget');

        $adGroups = Arr::wrap($configuration['ad_groups'] ?? []);
        if ($adGroups === []) {
            throw ValidationException::withMessages(['configuration.ad_groups' => 'Ajoute au moins un groupe d’annonces.']);
        }

        return [
            'campaign' => [
                'name' => $campaign->name,
                'status' => 'PAUSED',
                'channel' => 'SEARCH',
                'daily_budget' => (float) $configuration['daily_budget'],
                'currency' => $campaign->currency,
                'conversion_goal' => $configuration['conversion_goal'],
                'final_url' => $configuration['final_url'],
                'target_locations' => $configuration['target_locations'] ?? null,
                'languages' => $configuration['languages'] ?? null,
                'tracking_key' => $campaign->tracking_key,
            ],
            'ad_groups' => collect($adGroups)->map(function (array $group): array {
                foreach (['name', 'keywords', 'headlines', 'descriptions'] as $field) {
                    $this->requireValue($group, $field, 'configuration.ad_groups');
                }

                return [
                    'name' => $group['name'],
                    'keywords' => $this->lines($group['keywords']),
                    'negative_keywords' => $this->lines($group['negative_keywords'] ?? ''),
                    'headlines' => $this->lines($group['headlines']),
                    'descriptions' => $this->lines($group['descriptions']),
                ];
            })->all(),
        ];
    }

    /** @param array<string, mixed> $values */
    private function requireValue(array $values, string $key, string $prefix = 'configuration'): void
    {
        if (blank($values[$key] ?? null)) {
            throw ValidationException::withMessages(["{$prefix}.{$key}" => 'Cette valeur est requise avant la publication.']);
        }
    }

    /** @return array<int, string> */
    private function lines(string $value): array
    {
        return collect(preg_split('/\R/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
