<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Collection;
use LogicException;

class GoogleAdsCampaignConfigurationReader
{
    /** @param array<string, mixed> $credentials
     * @return array<string, mixed>
     */
    public function read(Campaign $campaign, array $credentials): array
    {
        if (! ctype_digit((string) $campaign->external_reference)) {
            throw new LogicException('Cette campagne n’est pas encore liée à une campagne Google Ads identifiable.');
        }

        $client = new GoogleAdsApiClient($credentials);
        $campaignId = (int) $campaign->external_reference;
        $campaignRow = $this->rows($client->searchStream(<<<GAQL
                    SELECT campaign.id, campaign.name, campaign.status
                    FROM campaign
                    WHERE campaign.id = {$campaignId}
                    GAQL,
        ))->first()['campaign'] ?? null;

        if (! is_array($campaignRow)) {
            throw new LogicException('Google Ads ne retrouve pas cette campagne dans le compte connecté.');
        }

        $budget = $this->rows($client->searchStream(<<<GAQL
                    SELECT campaign_budget.amount_micros, campaign_budget.total_amount_micros
                    FROM campaign
                    WHERE campaign.id = {$campaignId}
                    GAQL,
        ))->first()['campaignBudget'] ?? [];
        $groups = $this->rows($client->searchStream(<<<GAQL
                    SELECT ad_group.id, ad_group.name, ad_group.status
                    FROM ad_group
                    WHERE campaign.id = {$campaignId}
                    GAQL,
        ))->mapWithKeys(function (array $row): array {
            $group = $row['adGroup'] ?? [];
            $id = (string) ($group['id'] ?? '');

            return $id !== '' ? [$id => [
                'id' => $id,
                'name' => (string) ($group['name'] ?? 'Groupe sans nom'),
                'status' => $group['status'] ?? null,
                'keywords' => [],
                'negative_keywords' => [],
            ]] : [];
        })->all();
        $keywords = $this->rows($client->searchStream(<<<GAQL
                    SELECT ad_group.id, ad_group_criterion.negative,
                        ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type
                    FROM ad_group_criterion
                    WHERE campaign.id = {$campaignId}
                        AND ad_group_criterion.type = 'KEYWORD'
                    GAQL,
        ));

        foreach ($keywords as $row) {
            $groupId = (string) data_get($row, 'adGroup.id');
            $text = data_get($row, 'adGroupCriterion.keyword.text');

            if (! isset($groups[$groupId]) || ! is_string($text) || $text === '') {
                continue;
            }

            $field = data_get($row, 'adGroupCriterion.negative') ? 'negative_keywords' : 'keywords';
            $groups[$groupId][$field][] = $this->keyword($text, (string) data_get($row, 'adGroupCriterion.keyword.matchType'));
        }

        return [
            'campaign' => [
                'id' => (string) ($campaignRow['id'] ?? $campaignId),
                'name' => $campaignRow['name'] ?? null,
                'status' => $campaignRow['status'] ?? null,
                'daily_budget' => isset($budget['amountMicros']) ? ((float) $budget['amountMicros']) / 1_000_000 : null,
                'total_budget' => isset($budget['totalAmountMicros']) ? ((float) $budget['totalAmountMicros']) / 1_000_000 : null,
            ],
            'ad_groups' => collect($groups)->map(function (array $group): array {
                $group['keywords'] = array_values(array_unique($group['keywords']));
                $group['negative_keywords'] = array_values(array_unique($group['negative_keywords']));

                return $group;
            })->values()->all(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function rows(array $response): Collection
    {
        return collect($response)->pluck('results')->flatten(1)->filter(fn (mixed $row): bool => is_array($row))->values();
    }

    private function keyword(string $text, string $matchType): string
    {
        return match ($matchType) {
            'PHRASE' => '“'.$text.'”',
            'EXACT' => '['.$text.']',
            default => $text,
        };
    }
}
