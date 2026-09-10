<?php

namespace App\Filament\Resources\InstrumentAssets\Pages;

use App\Filament\Resources\InstrumentAssets\InstrumentAssetResource;
use App\Services\ContempoInstrumentPublisher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;

class EditInstrumentAsset extends EditRecord
{
    protected static string $resource = InstrumentAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('publishContempo')->label('Publier sur Contempo')->icon(Heroicon::OutlinedGlobeAlt)->requiresConfirmation()->action(function (ContempoInstrumentPublisher $publisher): void {
            try {
                $publisher->publish($this->record);
                Notification::make()->title('Instrument publié sur Contempo')->success()->send();
            } catch (LogicException $exception) {
                Notification::make()->title('Publication interrompue')->body($exception->getMessage())->danger()->send();
            }
        })];
    }
}
