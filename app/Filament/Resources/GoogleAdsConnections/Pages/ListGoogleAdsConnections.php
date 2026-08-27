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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configure')
                ->label('Préparer Google Ads')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (): bool => GoogleAdsConnectionResource::getEloquentQuery()->doesntExist())
                ->authorize(fn () => Gate::inspect('create', OrganizationIntegration::class))
                ->schema(GoogleAdsConnectionResource::configurationSchema())
                ->modalHeading('Préparer la connexion Google Ads')
                ->modalDescription('Aucun accès Google n’est déclenché ici. Vous pouvez enregistrer uniquement le numéro du compte et compléter le reste plus tard.')
                ->modalSubmitActionLabel('Enregistrer')
                ->action(fn (array $data) => GoogleAdsConnectionResource::save($data)),
        ];
    }
}
