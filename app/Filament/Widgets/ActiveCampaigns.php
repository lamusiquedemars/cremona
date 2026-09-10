<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ActiveCampaigns extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Campagnes actives')
            ->description('Accès direct au pilotage des campagnes actuellement en diffusion.')
            ->query(
                Campaign::query()
                    ->where('status', CampaignStatus::Active)
                    ->orderByDesc('google_ads_synced_at')
                    ->limit(5),
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Campagne')
                    ->description(fn (Campaign $record): string => $record->tracking_key)
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('google_ads_primary_status')
                    ->label('État Google')
                    ->formatStateUsing(fn (?string $state): string => $this->googleAdsPrimaryStatusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ELIGIBLE' => 'success',
                        'LEARNING', 'LIMITED' => 'warning',
                        'MISCONFIGURED', 'NOT_ELIGIBLE' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('À synchroniser'),
                TextColumn::make('daily_metrics_sum_spend')
                    ->label('Dépensé')
                    ->money(fn (Campaign $record): string => $record->currency)
                    ->hiddenFrom('md'),
                TextColumn::make('google_ads_synced_at')
                    ->label('Actualisée')
                    ->since()
                    ->placeholder('Jamais')
                    ->hiddenFrom('md'),
            ])
            ->recordUrl(fn (Campaign $record): string => CampaignResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('seeAll')
                    ->label('Voir les campagnes')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(CampaignResource::getUrl('index')),
            ])
            ->paginated(false);
    }

    private function googleAdsPrimaryStatusLabel(?string $status): string
    {
        return match ($status) {
            'ELIGIBLE' => 'Éligible',
            'LEARNING' => 'En apprentissage',
            'LIMITED' => 'Limitée',
            'MISCONFIGURED' => 'À corriger',
            'NOT_ELIGIBLE' => 'Non éligible',
            'PAUSED' => 'En pause',
            'PENDING' => 'En attente',
            'ENDED' => 'Terminée',
            'REMOVED' => 'Supprimée',
            default => $status ?? 'À synchroniser',
        };
    }
}
