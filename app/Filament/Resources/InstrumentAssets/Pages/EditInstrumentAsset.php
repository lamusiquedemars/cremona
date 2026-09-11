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
        return [Action::make('publishContempo')->label('Mettre à jour le site')->icon(Heroicon::OutlinedGlobeAlt)->requiresConfirmation()->action(function (ContempoInstrumentPublisher $publisher): void {
            try {
                $publisher->publish($this->record);
                Notification::make()->title('Site mis à jour')->success()->send();
            } catch (LogicException $exception) {
                Notification::make()->title('Publication interrompue')->body($exception->getMessage())->danger()->send();
            }
        })];
    }

    protected function afterSave(): void
    {
        if (! $this->record->wasChanged('is_site_published')) {
            return;
        }

        try {
            app(ContempoInstrumentPublisher::class)->publish($this->record);
            Notification::make()
                ->title($this->record->is_site_published ? 'Instrument publié sur le site' : 'Instrument retiré du site')
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
