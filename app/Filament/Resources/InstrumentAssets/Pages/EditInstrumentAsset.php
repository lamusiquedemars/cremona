<?php

namespace App\Filament\Resources\InstrumentAssets\Pages;

use App\Filament\Resources\InstrumentAssets\InstrumentAssetResource;
use App\Services\ContempoInstrumentPublisher;
use App\Services\InstrumentRentalPricing;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use App\Filament\Pages\BusinessEditRecord;
use LogicException;

class EditInstrumentAsset extends BusinessEditRecord
{
    protected static string $resource = InstrumentAssetResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['attributes', 'media'] as $field) {
            if (! is_string($data[$field] ?? null)) {
                continue;
            }

            $decoded = json_decode($data[$field], true);
            $data[$field] = is_array($decoded) ? $decoded : [];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->record->fill($data);
        app(InstrumentRentalPricing::class)->applyToInstrument($this->record);

        return $this->record->getAttributes();
    }

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
            'rental_size', 'rental_tier_id', 'rental_pricing_mode', 'rental_amount_override', 'instrument_category_id',
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
