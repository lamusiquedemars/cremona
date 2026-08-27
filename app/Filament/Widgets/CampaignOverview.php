<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Enums\IncomingRequestOutcome;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Models\CampaignDailyMetric;
use App\Models\IncomingRequest;
use App\Tenancy\OrganizationContext;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CampaignOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 30;

    protected ?string $heading = 'Pilotage des campagnes';

    protected ?string $description = 'Les 30 derniers jours : dépenses renseignées et demandes réellement reçues par les sites.';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    protected function getStats(): array
    {
        $since = now()->subDays(30)->toDateString();
        $active = Campaign::query()->where('status', CampaignStatus::Active)->count();
        $spend = (float) CampaignDailyMetric::query()->where('metric_date', '>=', $since)->sum('spend');
        $leads = IncomingRequest::query()
            ->where('received_at', '>=', now()->subDays(30))
            ->whereNotNull('attribution_campaign')
            ->count();
        $converted = IncomingRequest::query()
            ->where('received_at', '>=', now()->subDays(30))
            ->whereNotNull('attribution_campaign')
            ->where('outcome', IncomingRequestOutcome::Converted)
            ->count();

        return [
            Stat::make('Campagnes actives', $active)
                ->description('Celles actuellement en diffusion')
                ->icon(Heroicon::OutlinedMegaphone)
                ->color($active > 0 ? 'success' : 'gray')
                ->url(CampaignResource::getUrl('index')),
            Stat::make('Dépense renseignée', 'R$ '.number_format($spend, 2, ',', '.'))
                ->description('Somme des coûts journaliers saisis')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color($spend > 0 ? 'warning' : 'gray')
                ->url(CampaignResource::getUrl('index')),
            Stat::make('Demandes attribuées', $leads)
                ->description('Avec une clé de campagne reconnue')
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->color($leads > 0 ? 'info' : 'gray'),
            Stat::make('Demandes converties', $converted)
                ->description('Résultat commercial confirmé')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color($converted > 0 ? 'success' : 'gray'),
        ];
    }
}
