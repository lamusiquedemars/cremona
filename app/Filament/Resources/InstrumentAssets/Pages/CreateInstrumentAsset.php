<?php

namespace App\Filament\Resources\InstrumentAssets\Pages;

use App\Filament\Resources\InstrumentAssets\InstrumentAssetResource;
use App\Filament\Pages\BusinessCreateRecord;
use App\Services\ContempoInstrumentPublisher;
use App\Services\InstrumentRentalPricing;
use Filament\Notifications\Notification;
use LogicException;

class CreateInstrumentAsset extends BusinessCreateRecord
{
    protected static string $resource = InstrumentAssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $instrument = new \App\Models\InstrumentAsset($data);
        app(InstrumentRentalPricing::class)->applyToInstrument($instrument);

        return $instrument->getAttributes();
    }

    protected function afterCreate(): void
    {
        if (! $this->record->is_site_published) {
            return;
        }

        try {
            app(ContempoInstrumentPublisher::class)->publish($this->record);
            Notification::make()
                ->title('Site synchronisé')
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
