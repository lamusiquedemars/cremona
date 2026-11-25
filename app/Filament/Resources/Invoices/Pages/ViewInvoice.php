<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\InvoicePayment;
use App\Services\ElectronicInvoiceFinalizationManager;
use App\Services\SuperPdpTransmissionManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use LogicException;
use Throwable;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected array $extraBodyAttributes = [
        'class' => 'cremona-record-view',
    ];

    public function getTitle(): string|Htmlable
    {
        return ($this->record->reference ?: 'Brouillon').' — '.$this->record->title;
    }

    public function getBreadcrumb(): string
    {
        return $this->record->reference ?: 'Brouillon';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalizeAndTransmit')
                ->label('Finaliser et transmettre')->icon(Heroicon::OutlinedPaperAirplane)->color('primary')
                ->visible(fn (): bool => $this->record->status === InvoiceStatus::Draft || $this->record->transmissions()->where('provider', 'super_pdp')->whereIn('status', ['awaiting_configuration', 'ready_to_transmit', 'validated', 'validation_failed'])->exists())
                ->requiresConfirmation()
                ->modalDescription('La facture recevra son numéro définitif, puis sera contrôlée et remise au circuit de facturation électronique. Elle ne pourra plus être modifiée après sa finalisation.')
                ->action(function (ElectronicInvoiceFinalizationManager $manager): void {
                    try {
                        $result = $manager->finalizeAndTransmit($this->record);
                        $this->record->refresh();
                        Notification::make()
                            ->title($result['transmitted'] ? 'Facture finalisée et transmise' : 'Facture finalisée, document à corriger')
                            ->body($result['transmitted'] ? 'La remise est enregistrée. Les accusés de traitement seront synchronisés automatiquement.' : 'La facture n’a pas été transmise. Consultez le journal de transmission pour corriger le document.')
                            ->{$result['transmitted'] ? 'success' : 'warning'}()->send();
                    } catch (LogicException $exception) {
                        Notification::make()->title('Finalisation impossible')->body($exception->getMessage())->danger()->send();
                    } catch (Throwable) {
                        $this->record->refresh();
                        Notification::make()->title('Transmission à vérifier')->body('La facture peut avoir été finalisée. Vérifiez le journal avant toute nouvelle tentative.')->danger()->send();
                    }
                }),
            Action::make('downloadPdf')
                ->label('Télécharger le PDF')->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->record->pdfPrivateDocument !== null)
                ->url(fn (): string => route('private-documents.download', $this->record->pdfPrivateDocument?->public_id))
                ->openUrlInNewTab(),
            Action::make('synchronizeSuperPdp')
                ->label('Actualiser les statuts Super PDP')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn (): bool => $this->record->transmissions()->where('provider', 'super_pdp')->whereIn('status', ['submitted', 'sent', 'rejected', 'refused', 'invalid'])->exists())
                ->action(function (SuperPdpTransmissionManager $manager): void {
                    try {
                        $manager->synchronize($this->record);
                        $this->record->refresh();
                        Notification::make()->title('Statuts Super PDP actualisés')->success()->send();
                    } catch (LogicException $exception) {
                        Notification::make()->title('Actualisation impossible')->body($exception->getMessage())->danger()->send();
                    } catch (Throwable) {
                        Notification::make()->title('Actualisation impossible')->body('Super PDP a renvoyé une erreur technique.')->danger()->send();
                    }
                }),
            Action::make('reconcileUnknownSuperPdpSubmission')
                ->label('Rechercher la transmission dans Super PDP')->icon(Heroicon::OutlinedMagnifyingGlass)->color('warning')
                ->visible(fn (): bool => $this->record->transmissions()->where('provider', 'super_pdp')->where('status', 'submission_unknown')->exists())
                ->requiresConfirmation()
                ->modalDescription('Cette recherche ne transmet aucun document. Elle tente seulement de retrouver une facture déjà reçue par Super PDP.')
                ->action(function (SuperPdpTransmissionManager $manager): void {
                    try {
                        $manager->reconcileUnknownSubmission($this->record);
                        $this->record->refresh();
                        Notification::make()->title('Transmission Super PDP retrouvée')->success()->send();
                    } catch (LogicException $exception) {
                        Notification::make()->title('Transmission non retrouvée')->body($exception->getMessage())->warning()->send();
                    } catch (Throwable) {
                        Notification::make()->title('Recherche impossible')->body('Super PDP est temporairement inaccessible. Ne retransmettez pas la facture.')->danger()->send();
                    }
                }),
            Action::make('recordPayment')
                ->label('Enregistrer un règlement')->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn (): bool => in_array($this->record->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue], true))
                ->schema([
                    DatePicker::make('paid_on')->label('Date')->default(today())->required(),
                    TextInput::make('amount')->label('Montant')->numeric()->prefix('€')->default(fn (): string => (string) $this->record->balance_amount)->required(),
                    Select::make('method')->label('Mode')->options(['bank_transfer' => 'Virement', 'card' => 'Carte', 'cash' => 'Espèces', 'direct_debit' => 'Prélèvement', 'cheque' => 'Chèque', 'other' => 'Autre'])->required(),
                    TextInput::make('reference')->label('Référence bancaire'),
                ])
                ->action(function (array $data): void {
                    InvoicePayment::create(array_merge($data, ['invoice_id' => $this->record->id, 'recorded_by_user_id' => auth()->id()]));
                    $this->record->refresh();
                    Notification::make()->title('Règlement enregistré')->success()->send();
                }),
        ];
    }
}
