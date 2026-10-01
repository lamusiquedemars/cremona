<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Enums\RentalStatus;
use App\Filament\Resources\Rentals\RentalResource;
use App\Services\RentalManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Filament\Pages\BusinessEditRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;

class EditRental extends BusinessEditRecord
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
                ->schema([
                    DatePicker::make('returned_on')->label('Date de restitution')->default(today())->native(false)->required(),
                    Textarea::make('return_notes')->label('Constat ou observations')->rows(3),
                ])
                ->action(function (array $data, RentalManager $manager): void {
                    try {
                        $manager->return($this->record, \Illuminate\Support\Carbon::parse($data['returned_on']), $data['return_notes'] ?? null, auth()->user());
                    } catch (LogicException $exception) {
                        Notification::make()->title('Retour non enregistré')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Restitution enregistrée')->body('L’instrument redevient disponible. Le constat est conservé avec la location.')->success()->send();
                }),
        ];
    }
}
