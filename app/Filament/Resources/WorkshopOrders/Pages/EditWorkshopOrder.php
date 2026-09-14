<?php

namespace App\Filament\Resources\WorkshopOrders\Pages;

use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use App\Enums\WorkshopOrderStatus;
use App\Services\StockManager;
use App\Services\WorkshopOrderQuoteManager;
use App\Services\WorkshopOrderWorkflowManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;
use Illuminate\Support\HtmlString;

class EditWorkshopOrder extends EditRecord
{
    protected static string $resource = WorkshopOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmDiagnosis')
                ->label('Valider le diagnostic')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->visible(fn (): bool => $this->record->status === WorkshopOrderStatus::Received)
                ->requiresConfirmation()
                ->modalDescription('Le diagnostic doit d’abord être renseigné et enregistré. Cette action fait passer le dossier à « Diagnostic établi ».')
                ->action(function (WorkshopOrderWorkflowManager $manager): void {
                    try {
                        $manager->confirmDiagnosis($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Diagnostic non validé')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Diagnostic validé')->body('Le dossier est prêt à recevoir ou compléter son devis.')->success()->send();
                }),
            Action::make('syncQuote')->label('Créer ou compléter le devis')->icon(Heroicon::OutlinedDocumentCurrencyEuro)->visible(fn (): bool => in_array($this->record->status, [WorkshopOrderStatus::Diagnosed, WorkshopOrderStatus::AwaitingApproval], true))->action(function (WorkshopOrderQuoteManager $manager): void {
                try {
                    $quote = $manager->sync($this->record);
                } catch (LogicException $exception) {
                    Notification::make()->title('Devis non modifié')->body($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Devis mis à jour')->body("Les prestations et articles sélectionnés sont dans le devis {$quote->reference}.")->success()->send();
            }),
            Action::make('schedule')
                ->label('Planifier l’intervention')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->visible(fn (): bool => in_array($this->record->status, [WorkshopOrderStatus::Diagnosed, WorkshopOrderStatus::AwaitingApproval], true))
                ->schema([
                    DateTimePicker::make('due_at')->label('Intervention prévue le')->seconds(false)->required(),
                    Textarea::make('authorization_note')->label('Motif de l’accord sans devis')->rows(2)->helperText('À renseigner seulement lorsqu’aucun devis n’est lié au dossier.'),
                ])
                ->action(function (array $data, WorkshopOrderWorkflowManager $manager): void {
                    try {
                        $manager->schedule($this->record, \Illuminate\Support\Carbon::parse($data['due_at']), $data['authorization_note'] ?? null);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Intervention non planifiée')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Intervention planifiée')->success()->send();
                }),
            Action::make('start')
                ->label('Démarrer l’intervention')
                ->icon(Heroicon::OutlinedPlay)
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === WorkshopOrderStatus::Scheduled)
                ->requiresConfirmation()
                ->action(function (WorkshopOrderWorkflowManager $manager): void {
                    try {
                        $manager->start($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Intervention non démarrée')->body($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('applyStockConsumption')
                ->label('Déduire le stock consommé')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->requiresConfirmation()
                ->modalHeading('Déduire les articles consommés ?')
                ->modalDescription('Chaque article prévu et non encore déduit créera un mouvement de stock rattaché à ce dossier. Cette opération ne pourra pas être rejouée sur les mêmes lignes.')
                ->schema([
                    Placeholder::make('stock_preview')
                        ->label('Aperçu du mouvement')
                        ->content(fn (): HtmlString => new HtmlString($this->stockPreview())),
                ])
                ->modalSubmitActionLabel('Déduire le stock')
                ->action(function (StockManager $manager): void {
                    try {
                        $count = $manager->applyWorkshopConsumption($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Stock non modifié')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title($count === 0 ? 'Aucun article à déduire' : 'Stock mis à jour')->body($count === 0 ? 'Tous les articles de ce dossier ont déjà été déduits.' : "{$count} article(s) consommé(s) ont été enregistrés.")->success()->send();
                }),
            Action::make('markReady')
                ->label('Déclarer l’instrument prêt')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $this->record->status === WorkshopOrderStatus::InProgress)
                ->requiresConfirmation()
                ->modalDescription('L’heure « Prêt le » sera enregistrée. Aucun message client ne sera envoyé automatiquement.')
                ->action(function (WorkshopOrderWorkflowManager $manager): void {
                    try {
                        $manager->markReady($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Instrument non déclaré prêt')->body($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('markReturned')
                ->label('Enregistrer la restitution')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->visible(fn (): bool => $this->record->status === WorkshopOrderStatus::Ready)
                ->requiresConfirmation()
                ->action(function (WorkshopOrderWorkflowManager $manager): void {
                    try {
                        $manager->markReturned($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Restitution non enregistrée')->body($exception->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    private function stockPreview(): string
    {
        $lines = $this->record->stockItems()
            ->whereNull('applied_at')
            ->with('stockItem')
            ->get();

        if ($lines->isEmpty()) {
            return '<p>Aucun article supplémentaire ne sera déduit.</p>';
        }

        $items = $lines->map(function ($line): string {
            $before = (float) ($line->stockItem?->quantity_on_hand ?? 0);
            $after = $before - (float) $line->quantity;

            return '<li><strong>'.e($line->label_snapshot).'</strong> : −'.e((string) $line->quantity).' · '.e((string) $before).' → '.e((string) $after).'</li>';
        })->implode('');

        return '<ul class="list-disc space-y-1 ps-5">'.$items.'</ul>';
    }
}
