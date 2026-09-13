<?php

namespace App\Filament\Resources\WorkshopOrders\Pages;

use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use App\Services\StockManager;
use App\Services\WorkshopOrderQuoteManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;

class EditWorkshopOrder extends EditRecord
{
    protected static string $resource = WorkshopOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncQuote')->label('Créer ou compléter le devis')->icon(Heroicon::OutlinedDocumentCurrencyEuro)->action(function (WorkshopOrderQuoteManager $manager): void {
                try {
                    $quote = $manager->sync($this->record);
                } catch (LogicException $exception) {
                    Notification::make()->title('Devis non modifié')->body($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Devis mis à jour')->body("Les prestations et articles sélectionnés sont dans le devis {$quote->reference}.")->success()->send();
            }),
            Action::make('applyStockConsumption')
                ->label('Déduire le stock consommé')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->requiresConfirmation()
                ->modalHeading('Déduire les articles consommés ?')
                ->modalDescription('Chaque article prévu et non encore déduit créera un mouvement de stock rattaché à ce dossier. Cette opération ne pourra pas être rejouée sur les mêmes lignes.')
                ->action(function (StockManager $manager): void {
                    try {
                        $count = $manager->applyWorkshopConsumption($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Stock non modifié')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title($count === 0 ? 'Aucun article à déduire' : 'Stock mis à jour')->body($count === 0 ? 'Tous les articles de ce dossier ont déjà été déduits.' : "{$count} article(s) consommé(s) ont été enregistrés.")->success()->send();
                }),
        ];
    }
}
