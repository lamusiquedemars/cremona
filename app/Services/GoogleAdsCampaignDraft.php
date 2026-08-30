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
        $this->requireValue($configuration, 'target_locations');
        $this->requireValue($configuration, 'languages');
        $budgetMode = $configuration['budget_mode'] ?? 'daily';
        if (! in_array($budgetMode, ['daily', 'total'], true)) {
            throw ValidationException::withMessages(['configuration.budget_mode' => 'Le mode de budget Google Ads est invalide.']);
        }
        $budget = $this->budget($campaign, $configuration, $budgetMode);
        $targetCountry = strtoupper((string) ($configuration['target_country'] ?? 'FR'));
        if (! in_array($targetCountry, ['BR', 'FR'], true)) {
            throw ValidationException::withMessages(['configuration.target_country' => 'Le pays ciblé doit être BR ou FR.']);
        }

        $adGroups = Arr::wrap($configuration['ad_groups'] ?? []);
        if ($adGroups === []) {
            throw ValidationException::withMessages(['configuration.ad_groups' => 'Ajoute au moins un groupe d’annonces.']);
        }

        return [
            'campaign' => [
                'name' => $campaign->name,
                'status' => 'PAUSED',
                'channel' => 'SEARCH',
                ...$budget,
                'currency' => $campaign->currency,
                'conversion_goal' => $configuration['conversion_goal'],
                'final_url' => $this->trackingUrl($configuration['final_url'], $campaign->tracking_key),
                'target_locations' => $this->lines($configuration['target_locations']),
                'target_country' => $targetCountry,
                'languages' => $this->lines($configuration['languages']),
                'tracking_key' => $campaign->tracking_key,
            ],
            'ad_groups' => collect($adGroups)->map(function (array $group): array {
                foreach (['name', 'keywords', 'headlines', 'descriptions'] as $field) {
                    $this->requireValue($group, $field, 'configuration.ad_groups');
                }

                $headlines = $this->lines($group['headlines']);
                $descriptions = $this->lines($group['descriptions']);
                $this->validateAssets($headlines, $descriptions);

                return [
                    'name' => $group['name'],
                    'keywords' => $this->lines($group['keywords']),
                    'negative_keywords' => $this->lines($group['negative_keywords'] ?? ''),
                    'headlines' => $headlines,
                    'descriptions' => $descriptions,
                ];
            })->all(),
        ];
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function budget(Campaign $campaign, array $configuration, string $budgetMode): array
    {
        if ($budgetMode === 'daily') {
            $this->requireValue($configuration, 'daily_budget');

            return [
                'budget_mode' => 'daily',
                'daily_budget' => (float) $configuration['daily_budget'],
            ];
        }

        if ($campaign->starts_on === null || $campaign->ends_on === null || blank($campaign->planned_budget)) {
            throw ValidationException::withMessages(['planned_budget' => 'Un test borné requiert un budget total, une date de début et une date de fin.']);
        }
        if ($campaign->ends_on->lessThan($campaign->starts_on)) {
            throw ValidationException::withMessages(['ends_on' => 'La date de fin doit être postérieure ou égale à la date de début.']);
        }

        $duration = $campaign->starts_on->diffInDays($campaign->ends_on) + 1;
        if ($duration < 3 || $duration > 90) {
            throw ValidationException::withMessages(['ends_on' => 'Un budget total Google Ads requiert une période de 3 à 90 jours.']);
        }

        return [
            'budget_mode' => 'total',
            'total_budget' => (float) $campaign->planned_budget,
            'starts_on' => $campaign->starts_on->toDateString(),
            'ends_on' => $campaign->ends_on->toDateString(),
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

    private function trackingUrl(string $url, string $trackingKey): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => $trackingKey,
        ]);
    }

    /** @param array<int, string> $headlines @param array<int, string> $descriptions */
    private function validateAssets(array $headlines, array $descriptions): void
    {
        if (count($headlines) < 3 || count($headlines) > 15) {
            throw ValidationException::withMessages(['configuration.ad_groups.headlines' => 'Une annonce responsive requiert entre 3 et 15 titres.']);
        }
        if (count($descriptions) < 2 || count($descriptions) > 4) {
            throw ValidationException::withMessages(['configuration.ad_groups.descriptions' => 'Une annonce responsive requiert entre 2 et 4 descriptions.']);
        }
        foreach ($headlines as $headline) {
            if (mb_strlen($headline) > 30) {
                throw ValidationException::withMessages(['configuration.ad_groups.headlines' => "Le titre « {$headline} » dépasse 30 caractères."]);
            }
        }
        foreach ($descriptions as $description) {
            if (mb_strlen($description) > 90) {
                throw ValidationException::withMessages(['configuration.ad_groups.descriptions' => 'Une description dépasse 90 caractères.']);
            }
        }
    }
}
