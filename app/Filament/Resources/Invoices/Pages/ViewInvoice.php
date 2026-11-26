<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\InvoicePayment;
use App\Services\ElectronicInvoiceFinalizationManager;
use App\Services\ElectronicInvoiceFlow;
use App\Services\InvoiceCustomerCopyManager;
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
                ->label(fn (ElectronicInvoiceFlow $flow): string => $flow->actionLabel($this->record))->icon(Heroicon::OutlinedPaperAirplane)->color('primary')
                ->visible(fn (): bool => $this->record->status === InvoiceStatus::Draft)
                ->requiresConfirmation()
                ->modalDescription(fn (ElectronicInvoiceFlow $flow): string => $flow->confirmationDescription($this->record))
                ->action(fn (ElectronicInvoiceFinalizationManager $manager) => $this->processElectronicInvoice($manager, false)),
            Action::make('retryElectronicProcessing')
                ->label('Réessayer maintenant')->icon(Heroicon::OutlinedArrowPath)->color('warning')
                ->visible(fn (): bool => $this->record->status !== InvoiceStatus::Draft && $this->record->transmissions()->where('provider', 'super_pdp')->whereIn('status', ['awaiting_configuration', 'ready_to_transmit', 'validated', 'validation_failed'])->exists())
                ->requiresConfirmation()
                ->modalDescription('La facture est déjà créée. Cremona va seulement réessayer le traitement administratif qui n’a pas été confirmé ; son numéro et son contenu ne changeront pas.')
                ->action(fn (ElectronicInvoiceFinalizationManager $manager) => $this->processElectronicInvoice($manager, true)),
            Action::make('downloadPdf')
                ->label('Télécharger le PDF')->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->record->pdfPrivateDocument !== null)
                ->url(fn (): string => route('private-documents.download', $this->record->pdfPrivateDocument?->public_id))
                ->openUrlInNewTab(),
            Action::make('sendCustomerCopy')
                ->label('Envoyer une copie au client')->icon(Heroicon::OutlinedEnvelope)->color('gray')
                ->visible(fn (ElectronicInvoiceFlow $flow): bool => $this->record->issued_at !== null && $this->record->pdfPrivateDocument !== null && $flow->isB2c($this->record))
                ->schema([
                    TextInput::make('recipient_email')
                        ->label('Adresse e-mail du client')->email()
                        ->default(fn (): ?string => data_get($this->record->recipient_snapshot, 'email'))
                        ->required(),
                ])
                ->action(function (array $data, InvoiceCustomerCopyManager $manager): void {
                    try {
                        $delivery = $manager->sendByEmail($this->record, $data['recipient_email'], auth()->user());
                        Notification::make()->title('Copie de facture envoyée')->body('Envoyée à '.$delivery->recipient_email.'.')->success()->send();
                    } catch (LogicException $exception) {
                        Notification::make()->title('Envoi impossible')->body($exception->getMessage())->danger()->send();
                    }
                }),
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
                ->label('Vérifier la transmission')->icon(Heroicon::OutlinedMagnifyingGlass)->color('warning')
                ->visible(fn (): bool => $this->record->transmissions()->where('provider', 'super_pdp')->where('status', 'submission_unknown')->exists())
                ->requiresConfirmation()
                ->modalDescription('Cette recherche ne transmet aucun document. Elle vérifie seulement si la plateforme a déjà reçu la facture.')
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

    private function processElectronicInvoice(ElectronicInvoiceFinalizationManager $manager, bool $retry): void
    {
        try {
            $result = $manager->finalizeAndTransmit($this->record);
            $this->record->refresh();
            Notification::make()
                ->title($result['submitted'] ? ($retry ? 'Traitement confirmé' : ($result['b2c'] ? 'Facture finalisée' : 'Facture finalisée et transmise')) : 'Facture finalisée, document à corriger')
                ->body($result['submitted'] ? ($result['b2c'] ? 'Les données B2C sont prises en charge pour l’e-reporting. Remettez le PDF au client par le canal approprié.' : 'La remise est enregistrée. Les accusés de traitement seront synchronisés automatiquement.') : 'La facture n’a pas été remise à la plateforme. Consultez le journal de transmission pour corriger le document.')
                ->{$result['submitted'] ? 'success' : 'warning'}()->send();
        } catch (LogicException $exception) {
            Notification::make()->title($retry ? 'Nouvel essai impossible' : 'Finalisation impossible')->body($exception->getMessage())->danger()->send();
        } catch (Throwable $exception) {
            $this->record->refresh();
            report($exception);
            $problem = $this->record->transmissions()->where('provider', 'super_pdp')->latest()->value('last_error');
            Notification::make()->title('Traitement à réessayer')->body($problem ?: 'La facture peut avoir été finalisée, mais la plateforme n’a pas confirmé le traitement. Consultez le journal avant toute nouvelle tentative.')->danger()->send();
        }
    }
}
