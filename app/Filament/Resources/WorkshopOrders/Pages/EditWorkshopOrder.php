<?php

namespace App\Filament\Resources\WorkshopOrders\Pages;

use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use App\Services\WorkshopOrderQuoteManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditWorkshopOrder extends EditRecord
{
    protected static string $resource = WorkshopOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('syncQuote')->label('Créer ou compléter le devis')->icon(Heroicon::OutlinedDocumentCurrencyEuro)->action(function (WorkshopOrderQuoteManager $manager): void {
            $quote = $manager->sync($this->record);
            Notification::make()->title('Devis mis à jour')->body("Les prestations sélectionnées sont dans le devis {$quote->reference}.")->success()->send();
        })];
    }
}
