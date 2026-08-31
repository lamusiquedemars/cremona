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
use LogicException;
use Throwable;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_google_ads')
                ->label('Actualiser les résultats')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->visible(fn (): bool => $this->record->channel === 'google_ads')
                ->authorize('update')
                ->requiresConfirmation()
                ->modalDescription('Cette action lit les résultats Google Ads des 30 derniers jours. Elle ne modifie ni la campagne, ni son budget, ni sa diffusion.')
                ->action(function (): void {
                    $integration = OrganizationIntegration::query()
                        ->where('provider', 'google_ads')
                        ->where('name', 'reporting')
                        ->first();

                    if ($integration === null || ! GoogleAdsConnectionResource::isReady($integration->credentials)) {
                        Notification::make()
                            ->title('Connexion Google Ads à préparer')
                            ->body('La synchronisation nécessite une connexion prête dans « Configuration de l’organisation > Publicité ».')
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    try {
                        app(GoogleAdsReportingClient::class)->sync($integration);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Synchronisation Google Ads arrêtée')->body($exception->getMessage())->danger()->persistent()->send();

                        return;
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title('Synchronisation Google Ads interrompue')->body('Google Ads n’a pas pu fournir les résultats. Réessaie dans quelques minutes.')->danger()->persistent()->send();

                        return;
                    }

                    $this->record->refresh();
                    Notification::make()->title('Résultats Google Ads actualisés')->body('Les observations disponibles des 30 derniers jours ont été enregistrées.')->success()->send();
                }),
            EditAction::make()->label('Modifier la configuration'),
        ];
    }
}
