<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Tenancy\OrganizationContext;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class ActiveCampaigns extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.active-campaigns';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    /** @return array{campaigns: array<int, array<string, string>>} */
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
                'google_status' => $this->googleAdsPrimaryStatusLabel($campaign->google_ads_primary_status),
                'spend' => $campaign->spend_last_30_days === null
                    ? 'Aucune dépense renseignée'
                    : Number::currency((float) $campaign->spend_last_30_days, $campaign->currency, 'fr'),
                'leads' => $campaign->recent_leads_count.' demande'.($campaign->recent_leads_count > 1 ? 's' : '').' issue'.($campaign->recent_leads_count > 1 ? 's' : '').' de cette campagne',
                'synced_at' => $campaign->google_ads_synced_at?->setTimezone($organization->timezone())->diffForHumans() ?? 'Google Ads non actualisé',
            ])
            ->all();

        return ['campaigns' => $campaigns];
    }

    private function googleAdsPrimaryStatusLabel(?string $status): string
    {
        return match ($status) {
            'ELIGIBLE' => 'Diffusion possible',
            'LEARNING' => 'En apprentissage',
            'LIMITED' => 'Diffusion limitée',
            'MISCONFIGURED' => 'À corriger',
            'NOT_ELIGIBLE' => 'Non éligible',
            'PAUSED' => 'En pause',
            'PENDING' => 'En attente',
            'ENDED' => 'Terminée',
            'REMOVED' => 'Supprimée',
            default => 'État Google à actualiser',
        };
    }
}
