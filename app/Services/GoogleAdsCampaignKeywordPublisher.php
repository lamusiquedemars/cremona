<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\OrganizationIntegration;
use App\Models\User;
use LogicException;

class GoogleAdsCampaignKeywordPublisher
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly GoogleAdsCampaignConfigurationReader $configurationReader,
        private readonly GoogleAdsCredentials $credentials,
    ) {}

    /**
     * Apply only the keyword preparation of groups which exist on both sides.
     *
     * @return array{created: int, removed: int, groups: int}
     */
    public function apply(Campaign $campaign, OrganizationIntegration $integration, ?User $actor = null): array
    {
        if ($campaign->channel !== 'google_ads' || ! ctype_digit((string) $campaign->external_reference)) {
            throw new LogicException('Cette campagne n’est pas encore liée à une campagne Google Ads identifiable.');
        }
        if ($integration->provider !== 'google_ads' || $integration->name !== 'reporting') {
            throw new LogicException('La connexion Google Ads de l’organisation est introuvable.');
        }
        if (! $this->credentials->isReady($integration->credentials)) {
            throw new LogicException('Google Ads n’est pas encore entièrement configuré.');
        }

        $resolvedCredentials = $this->credentials->resolve($integration->credentials);
        $customerId = preg_replace('/\D/', '', (string) ($resolvedCredentials['customer_id'] ?? ''));

        if ($customerId === '') {
            throw new LogicException('L’identifiant du compte Google Ads est invalide.');
        }

        // The snapshot is intentionally refreshed immediately before a write.
        $snapshot = $this->configurationReader->read($campaign, $resolvedCredentials);
        $campaign->update([
            'google_ads_configuration' => $snapshot,
            'google_ads_configuration_synced_at' => now(),
        ]);

        $remoteGroups = collect($snapshot['ad_groups'] ?? [])
            ->filter(fn (mixed $group): bool => is_array($group) && $this->groupKey($group) !== '')
            ->keyBy(fn (array $group): string => $this->groupKey($group));
        $operations = [];
        $matchedGroups = 0;

        foreach ($campaign->configuration['ad_groups'] ?? [] as $group) {
            if (! is_array($group) || ($remote = $remoteGroups->get($this->groupKey($group))) === null) {
                continue;
            }

            $matchedGroups++;
            $remoteCriteria = collect($remote['keyword_criteria'] ?? [])
                ->filter(fn (mixed $criterion): bool => is_array($criterion) && filled($criterion['criterion_id'] ?? null))
                ->groupBy(fn (array $criterion): string => $this->criterionKey(
                    (string) ($criterion['text'] ?? ''),
                    (bool) ($criterion['negative'] ?? false),
                ));

            foreach ([false => 'keywords', true => 'negative_keywords'] as $negative => $field) {
                $desired = collect($this->keywords($group[$field] ?? null))
                    ->keyBy(fn (string $keyword): string => $this->criterionKey($keyword, $negative));

                foreach ($remoteCriteria as $key => $criteria) {
                    $isNegative = (bool) ($criteria->first()['negative'] ?? false);

                    if ($isNegative !== $negative || $desired->has($key)) {
                        if ($isNegative === $negative && $desired->has($key)) {
                            foreach ($criteria->skip(1) as $criterion) {
                                $operations[] = ['remove' => "customers/{$customerId}/adGroupCriteria/{$remote['id']}~{$criterion['criterion_id']}"];
                            }
                        }

                        continue;
                    }

                    foreach ($criteria as $criterion) {
                        $operations[] = ['remove' => "customers/{$customerId}/adGroupCriteria/{$remote['id']}~{$criterion['criterion_id']}"];
                    }
                }

                foreach ($desired as $key => $keyword) {
                    if ($remoteCriteria->has($key)) {
                        continue;
                    }

                    $create = ['adGroup' => "customers/{$customerId}/adGroups/{$remote['id']}"];

                    if ($negative) {
                        $create['negative'] = true;
                    } else {
                        $create['status'] = 'ENABLED';
                    }
                    $create['keyword'] = $this->keywordPayload($keyword);

                    $operations[] = ['create' => $create];
                }
            }
        }

        if ($matchedGroups === 0) {
            throw new LogicException('Aucun groupe Cremona ne porte le même nom qu’un groupe Google Ads : rien n’a été modifié.');
        }

        $created = count(array_filter($operations, fn (array $operation): bool => isset($operation['create'])));
        $removed = count($operations) - $created;

        if ($operations !== []) {
            foreach (array_chunk($operations, 500) as $chunk) {
                (new GoogleAdsApiClient($resolvedCredentials))->mutate('adGroupCriteria', $chunk);
            }
        }

        $result = ['created' => $created, 'removed' => $removed, 'groups' => $matchedGroups];
        $this->auditLogger->record('campaign.google_ads_keywords_applied', $campaign, $actor, $result);

        return $result;
    }

    /** @return array<int, string> */
    private function keywords(mixed $keywords): array
    {
        return collect(is_string($keywords) ? preg_split('/\R/', $keywords) : (array) $keywords)
            ->filter(fn (mixed $keyword): bool => is_string($keyword) && trim($keyword) !== '')
            ->map(fn (string $keyword): string => trim($keyword))
            ->unique(fn (string $keyword): string => $this->criterionKey($keyword, false))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $group */
    private function groupKey(array $group): string
    {
        return mb_strtolower(trim((string) ($group['name'] ?? '')));
    }

    private function criterionKey(string $keyword, bool $negative): string
    {
        return ($negative ? 'negative:' : 'keyword:').mb_strtolower(trim(str_replace(['“', '”'], '"', $keyword)));
    }

    /** @return array{text: string, matchType: string} */
    private function keywordPayload(string $keyword): array
    {
        $keyword = trim(str_replace(['“', '”'], '"', $keyword));
        $matchType = 'BROAD';

        if (preg_match('/^\[(.+)]$/u', $keyword, $matches)) {
            $keyword = trim($matches[1]);
            $matchType = 'EXACT';
        } elseif (preg_match('/^"(.+)"$/u', $keyword, $matches)) {
            $keyword = trim($matches[1]);
            $matchType = 'PHRASE';
        }

        if ($keyword === '') {
            throw new LogicException('Un mot-clé ne peut pas être vide.');
        }

        return ['text' => $keyword, 'matchType' => $matchType];
    }
}
