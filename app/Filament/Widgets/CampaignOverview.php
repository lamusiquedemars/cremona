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
use Illuminate\Support\Number;

class CampaignOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 15;

    protected ?string $heading = 'Vue d’ensemble des campagnes';

    protected ?string $description = 'Les 30 derniers jours.';

    /**
     * Les indicateurs restent confortables au toucher sur téléphone.
     *
     * @var array<string, int>
     */
    protected int|array|null $columns = ['sm' => 2, 'xl' => 4];

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
        $organization = app(OrganizationContext::class)->require();
        $since = now()->setTimezone($organization->timezone())->subDays(30)->toDateString();
        $active = Campaign::query()->where('status', CampaignStatus::Active)->count();
        $spendByCurrency = CampaignDailyMetric::query()
            ->where('metric_date', '>=', $since)
            ->selectRaw('currency, SUM(spend) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');
        $spend = $spendByCurrency
            ->map(fn (mixed $total, string $currency): string => Number::currency((float) $total, $currency, 'fr'))
            ->implode(' · ');
        $windowStart = now()->setTimezone($organization->timezone())->subDays(30);
        $leads = IncomingRequest::query()
            ->where('received_at', '>=', $windowStart)
            ->whereNotNull('attribution_campaign')
            ->count();
        $converted = IncomingRequest::query()
            ->where('received_at', '>=', $windowStart)
            ->whereNotNull('attribution_campaign')
            ->where('outcome', IncomingRequestOutcome::Converted)
            ->count();

        return [
            Stat::make('Campagnes actives', $active)
                ->description('En diffusion actuellement')
                ->icon(Heroicon::OutlinedMegaphone)
                ->color($active > 0 ? 'success' : 'gray')
                ->url(CampaignResource::getUrl('index')),
            Stat::make('Budget dépensé', $spend !== '' ? $spend : '—')
                ->description($spendByCurrency->count() > 1
                    ? 'Total affiché séparément pour chaque devise.'
                    : 'Dépenses enregistrées sur la période.')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color($spend !== '' ? 'warning' : 'gray')
                ->url(CampaignResource::getUrl('index')),
            Stat::make('Demandes issues des campagnes', $leads)
                ->description('Demandes du site reliées à une campagne')
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->color($leads > 0 ? 'info' : 'gray'),
            Stat::make('Demandes concrétisées', $converted)
                ->description('Demandes marquées comme gagnées')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color($converted > 0 ? 'success' : 'gray'),
        ];
    }
}
