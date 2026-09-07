<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\GoogleAdsConnections\GoogleAdsConnectionResource;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsReportingClient;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Throwable;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->needsGoogleAdsRefresh()) {
            $this->synchronizeGoogleAds(false);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_google_ads')
                ->label('Actualiser maintenant')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->visible(fn (): bool => $this->record->channel === 'google_ads' && filled($this->record->external_reference))
                ->authorize('update')
                ->action(function (): void {
                    $this->synchronizeGoogleAds(true);
                }),
            EditAction::make()->label(fn (): string => filled($this->record->external_reference)
                ? 'Modifier la préparation Cremona'
                : 'Modifier le brouillon'),
        ];
    }

    private function needsGoogleAdsRefresh(): bool
    {
        return $this->record->channel === 'google_ads'
            && filled($this->record->external_reference)
            && Gate::allows('update', $this->record)
            && ($this->record->google_ads_synced_at === null || $this->record->google_ads_synced_at->lt(now()->subMinutes(15)));
    }

    private function synchronizeGoogleAds(bool $announce): void
    {
        $integration = OrganizationIntegration::query()
            ->where('provider', 'google_ads')
            ->where('name', 'reporting')
            ->first();

        if ($integration === null || ! GoogleAdsConnectionResource::isReady($integration->credentials)) {
            if ($announce) {
                Notification::make()
                    ->title('Connexion Google Ads à préparer')
                    ->body('La synchronisation nécessite une connexion prête dans « Configuration de l’organisation > Publicité ».')
                    ->warning()
                    ->persistent()
                    ->send();
            }

            return;
        }

        try {
            app(GoogleAdsReportingClient::class)->syncCampaign($this->record, $integration);
            $this->record->refresh();
        } catch (LogicException $exception) {
            if ($announce) {
                Notification::make()->title('Synchronisation Google Ads arrêtée')->body($exception->getMessage())->danger()->persistent()->send();
            }

            return;
        } catch (Throwable $exception) {
            report($exception);

            if ($announce) {
                Notification::make()->title('Synchronisation Google Ads interrompue')->body('Google Ads n’a pas pu fournir les résultats. Réessaie dans quelques minutes.')->danger()->persistent()->send();
            }

            return;
        }

        if ($announce) {
            Notification::make()->title('Résultats Google Ads actualisés')->body('État et résultats des 30 derniers jours enregistrés.')->success()->send();
        }
    }
}
