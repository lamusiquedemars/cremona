<?php

namespace App\Filament\Resources\InstrumentAssets\Pages;

use App\Filament\Resources\InstrumentAssets\InstrumentAssetResource;
use App\Services\ContempoInstrumentPublisher;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use App\Filament\Pages\BusinessEditRecord;
use LogicException;

class EditInstrumentAsset extends BusinessEditRecord
{
    protected static string $resource = InstrumentAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Supprimer l’instrument')
                ->modalHeading('Supprimer cet instrument ?')
                ->modalDescription('Cette suppression est définitive. Elle reste indisponible tant que l’instrument est publié sur le site ou lié à une location, afin de préserver la cohérence des données.')
                ->disabled(fn (): bool => $this->record->is_site_published || $this->record->rentals()->exists())
                ->tooltip(function (): ?string {
                    if ($this->record->is_site_published) {
                        return 'Retirez d’abord cet instrument du site, puis enregistrez la fiche avant de le supprimer.';
                    }

                    return $this->record->rentals()->exists()
                        ? 'Cet instrument est lié à une ou plusieurs locations et ne peut pas être supprimé.'
                        : null;
                }),
        ];
    }

    protected function afterSave(): void
    {
        $publicFields = [
            'name', 'reference', 'family', 'maker', 'description', 'attributes', 'media', 'status',
            'available_for_sale', 'suggested_sale_amount', 'available_for_rental', 'suggested_rental_amount',
            'is_site_published', 'public_slug', 'public_title', 'public_description', 'public_price_label',
        ];

        if (! $this->record->wasChanged($publicFields)
            || (! $this->record->is_site_published && ! $this->record->wasChanged('is_site_published'))) {
            return;
        }

        try {
            app(ContempoInstrumentPublisher::class)->publish($this->record);
            Notification::make()
                ->title($this->record->is_site_published ? 'Site synchronisé' : 'Instrument retiré du site')
                ->success()
                ->send();
        } catch (LogicException $exception) {
            Notification::make()
                ->title('Mise à jour du site interrompue')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
