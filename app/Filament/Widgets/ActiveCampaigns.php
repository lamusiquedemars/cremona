<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class ActiveCampaigns extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.active-campaigns';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && app(OrganizationModuleAccess::class)->enabled('marketing', $organization)
            && Campaign::query()->where('status', CampaignStatus::Active)->exists()
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    /** @return array{campaigns: array<int, array<string, string|null>>} */
    protected function getViewData(): array
    {
        $organization = app(OrganizationContext::class)->require();
        $since = now($organization->timezone())->subDays(30)->toDateString();
        $locale = app()->getLocale();

        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->withSum(['dailyMetrics as spend_last_30_days' => fn (Builder $query): Builder => $query->where('metric_date', '>=', $since)], 'spend')
            ->withSum(['dailyMetrics as impressions_last_30_days' => fn (Builder $query): Builder => $query->where('metric_date', '>=', $since)], 'impressions')
            ->withSum(['dailyMetrics as clicks_last_30_days' => fn (Builder $query): Builder => $query->where('metric_date', '>=', $since)], 'clicks')
            ->withCount(['attributedIncomingRequests as recent_leads_count' => fn (Builder $query): Builder => $query->where('received_at', '>=', $since)])
            ->orderByDesc('google_ads_synced_at')
            ->limit(3)
            ->get()
            ->map(fn (Campaign $campaign): array => [
                'name' => $campaign->name,
                'url' => CampaignResource::getUrl('view', ['record' => $campaign]),
                'google_status' => $this->googleAdsStatusLabel($campaign->google_ads_serving_status, $campaign->google_ads_primary_status),
                'spend' => $campaign->spend_last_30_days === null ? __('cremona.dashboard.no_recorded_spend') : Number::currency((float) $campaign->spend_last_30_days, $campaign->currency, $locale),
                'impressions' => $campaign->impressions_last_30_days === null ? '—' : Number::format((int) $campaign->impressions_last_30_days, locale: $locale),
                'clicks' => $campaign->clicks_last_30_days === null ? '—' : Number::format((int) $campaign->clicks_last_30_days, locale: $locale),
                'leads' => trans_choice('cremona.dashboard.requests_from_campaign', $campaign->recent_leads_count, ['count' => $campaign->recent_leads_count]),
                'synced_at' => $campaign->google_ads_synced_at?->setTimezone($organization->timezone())->locale($locale)->diffForHumans() ?? __('cremona.dashboard.google_ads_not_synced'),
            ])
            ->all();

        return ['campaigns' => $campaigns];
    }

    private function googleAdsStatusLabel(?string $servingStatus, ?string $primaryStatus): ?string
    {
        return match ($servingStatus) {
            'SERVING' => __('cremona.dashboard.campaign_status.serving'),
            'NONE' => __('cremona.dashboard.campaign_status.not_serving'),
            'SUSPENDED' => __('cremona.dashboard.campaign_status.suspended'),
            'ENDED' => __('cremona.dashboard.campaign_status.ended'),
            'PENDING' => __('cremona.dashboard.campaign_status.pending'),
            default => match ($primaryStatus) {
                'ELIGIBLE' => null,
                'LEARNING' => __('cremona.dashboard.campaign_status.learning'),
                'LIMITED' => __('cremona.dashboard.campaign_status.limited'),
                'MISCONFIGURED' => __('cremona.dashboard.campaign_status.to_fix'),
                'NOT_ELIGIBLE' => __('cremona.dashboard.campaign_status.ineligible'),
                'PAUSED' => __('cremona.dashboard.campaign_status.paused'),
                'PENDING' => __('cremona.dashboard.campaign_status.pending'),
                'ENDED' => __('cremona.dashboard.campaign_status.ended'),
                'REMOVED' => __('cremona.dashboard.campaign_status.removed'),
                default => null,
            },
        };
    }
}
