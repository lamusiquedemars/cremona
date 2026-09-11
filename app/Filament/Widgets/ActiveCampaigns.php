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

        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->withSum([
                'dailyMetrics as spend_last_30_days' => fn (Builder $query): Builder => $query
                    ->where('metric_date', '>=', $since),
            ], 'spend')
            ->withSum([
                'dailyMetrics as impressions_last_30_days' => fn (Builder $query): Builder => $query
                    ->where('metric_date', '>=', $since),
            ], 'impressions')
            ->withSum([
                'dailyMetrics as clicks_last_30_days' => fn (Builder $query): Builder => $query
                    ->where('metric_date', '>=', $since),
            ], 'clicks')
            ->withCount([
                'attributedIncomingRequests as recent_leads_count' => fn (Builder $query): Builder => $query
                    ->where('received_at', '>=', $since),
            ])
            ->orderByDesc('google_ads_synced_at')
            ->limit(3)
            ->get()
            ->map(fn (Campaign $campaign): array => [
                'name' => $campaign->name,
                'url' => CampaignResource::getUrl('view', ['record' => $campaign]),
                'google_status' => $this->googleAdsStatusLabel($campaign->google_ads_serving_status, $campaign->google_ads_primary_status),
                'spend' => $campaign->spend_last_30_days === null
                    ? 'Aucune dépense renseignée'
                    : Number::currency((float) $campaign->spend_last_30_days, $campaign->currency, 'fr'),
                'impressions' => $campaign->impressions_last_30_days === null
                    ? '—'
                    : Number::format((int) $campaign->impressions_last_30_days, locale: 'fr'),
                'clicks' => $campaign->clicks_last_30_days === null
                    ? '—'
                    : Number::format((int) $campaign->clicks_last_30_days, locale: 'fr'),
                'leads' => $campaign->recent_leads_count.' demande'.($campaign->recent_leads_count > 1 ? 's' : '').' issue'.($campaign->recent_leads_count > 1 ? 's' : '').' de cette campagne',
                'synced_at' => $campaign->google_ads_synced_at?->setTimezone($organization->timezone())->diffForHumans() ?? 'Google Ads non actualisé',
            ])
            ->all();

        return ['campaigns' => $campaigns];
    }

    private function googleAdsStatusLabel(?string $servingStatus, ?string $primaryStatus): ?string
    {
        return match ($servingStatus) {
            'SERVING' => 'En diffusion',
            'NONE' => 'Hors diffusion',
            'SUSPENDED' => 'Suspendue',
            'ENDED' => 'Terminée',
            'PENDING' => 'En attente',
            default => match ($primaryStatus) {
                'ELIGIBLE' => null,
                'LEARNING' => 'En apprentissage',
                'LIMITED' => 'Diffusion limitée',
                'MISCONFIGURED' => 'À corriger',
                'NOT_ELIGIBLE' => 'Non éligible',
                'PAUSED' => 'En pause',
                'PENDING' => 'En attente',
                'ENDED' => 'Terminée',
                'REMOVED' => 'Supprimée',
                default => null,
            },
        };
    }
}
