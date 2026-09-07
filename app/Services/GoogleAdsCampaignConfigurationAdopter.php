<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\User;
use LogicException;

class GoogleAdsCampaignConfigurationAdopter
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function adoptKeywords(Campaign $campaign, User $actor): int
    {
        $remoteGroups = collect($campaign->google_ads_configuration['ad_groups'] ?? [])
            ->keyBy(fn (array $group): string => mb_strtolower(trim((string) ($group['name'] ?? ''))));
        $configuration = $campaign->configuration ?? [];
        $updated = 0;

        foreach ($configuration['ad_groups'] ?? [] as $index => $group) {
            $remote = $remoteGroups->get(mb_strtolower(trim((string) ($group['name'] ?? ''))));

            if (! is_array($remote)) {
                continue;
            }

            $configuration['ad_groups'][$index]['keywords'] = implode("\n", $remote['keywords'] ?? []);
            $configuration['ad_groups'][$index]['negative_keywords'] = implode("\n", $remote['negative_keywords'] ?? []);
            $updated++;
        }

        if ($updated === 0) {
            throw new LogicException('Aucun groupe Google Ads correspondant ne peut être adopté dans la préparation Cremona.');
        }

        $campaign->update(['configuration' => $configuration]);
        $this->auditLogger->record('campaign.google_ads_keywords_adopted', $campaign, $actor, ['updated_groups' => $updated]);

        return $updated;
    }
}
