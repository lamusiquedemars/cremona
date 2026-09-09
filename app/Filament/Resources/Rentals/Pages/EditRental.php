<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Enums\RentalStatus;
use App\Filament\Resources\Rentals\RentalResource;
use App\Services\RentalManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;

class EditRental extends EditRecord
{
    protected static string $resource = RentalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Démarrer la location')
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->visible(fn (): bool => $this->record->status === RentalStatus::Draft)
                ->requiresConfirmation()
                ->action(function (RentalManager $manager): void {
                    try {
                        $manager->activate($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Location non démarrée')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Location démarrée')->body('L’instrument est maintenant indiqué comme en location.')->success()->send();
                }),
            Action::make('return')
                ->label('Enregistrer le retour')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->visible(fn (): bool => $this->record->status === RentalStatus::Active)
                ->requiresConfirmation()
                ->action(function (RentalManager $manager): void {
                    try {
                        $manager->return($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Retour non enregistré')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Retour enregistré')->body('L’instrument redevient disponible.')->success()->send();
                }),
        ];
    }
}
