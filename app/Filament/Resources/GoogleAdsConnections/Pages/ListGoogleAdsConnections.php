<?php

namespace App\Filament\Resources\GoogleAdsConnections\Pages;

use App\Filament\Resources\GoogleAdsConnections\GoogleAdsConnectionResource;
use App\Models\OrganizationIntegration;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ListGoogleAdsConnections extends ListRecords
{
    protected static string $resource = GoogleAdsConnectionResource::class;

    public function getHeading(): string
    {
        return 'Compte Google Ads';
    }

    public function getSubheading(): ?string
    {
        return 'Reliez le compte publicitaire détenu par cette organisation. La synchronisation lit uniquement les résultats des campagnes ; elle ne crée et ne modifie aucune campagne Google Ads.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configure')
                ->label('Relier un compte Google Ads')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (): bool => GoogleAdsConnectionResource::getEloquentQuery()->doesntExist())
                ->authorize(fn () => Gate::inspect('create', OrganizationIntegration::class))
                ->schema(GoogleAdsConnectionResource::configurationSchema())
                ->modalHeading('Compte Google Ads de l’organisation')
                ->modalDescription('Enregistrez le numéro du compte client. Aucun secret ni réglage de l’infrastructure Maracuja n’est affiché ici.')
                ->modalSubmitActionLabel('Enregistrer')
                ->action(fn (array $data) => GoogleAdsConnectionResource::save($data)),
        ];
    }
}
