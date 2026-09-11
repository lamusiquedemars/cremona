<?php

namespace App\Filament\Resources\InstrumentAssets\Pages;

use App\Filament\Resources\InstrumentAssets\InstrumentAssetResource;
use App\Services\ContempoInstrumentPublisher;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use LogicException;

class EditInstrumentAsset extends EditRecord
{
    protected static string $resource = InstrumentAssetResource::class;

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
