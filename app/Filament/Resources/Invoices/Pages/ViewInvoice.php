<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\InvoicePayment;
use App\Services\InvoiceElectronicDocumentManager;
use App\Services\InvoiceWorkflowManager;
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
            Action::make('issue')
                ->label('Émettre la facture')->icon(Heroicon::OutlinedPaperAirplane)->color('primary')
                ->visible(fn (): bool => $this->record->status === InvoiceStatus::Draft)
                ->requiresConfirmation()->modalDescription('La facture recevra son numéro définitif et ses données ne pourront plus être modifiées.')
                ->action(function (InvoiceWorkflowManager $manager): void {
                    try {
                        $manager->issue($this->record);
                        $this->record->refresh();
                        Notification::make()->title('Facture émise')->body('Le numéro définitif a été attribué. La transmission Super PDP reste en attente de la connexion de l’organisation.')->success()->send();
                    } catch (LogicException $exception) {
                        Notification::make()->title('Facture non émise')->body($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('generateElectronicDocument')
                ->label('Valider le document électronique')->icon(Heroicon::OutlinedShieldCheck)->color('warning')
                ->visible(fn (): bool => $this->record->status !== InvoiceStatus::Draft && $this->record->transmissions()->where('provider', 'super_pdp')->whereIn('status', ['awaiting_configuration', 'ready_to_transmit', 'validated', 'validation_failed'])->exists())
                ->requiresConfirmation()
                ->modalHeading('Générer et valider le document CII')
                ->modalDescription('Super PDP convertira et validera le document électronique. Cette action ne transmet pas la facture au client ni à l’administration.')
                ->action(function (InvoiceElectronicDocumentManager $manager): void {
                    try {
                        $result = $manager->generateAndValidate($this->record);
                        $this->record->refresh();
                        Notification::make()
                            ->title($result['valid'] ? 'Document électronique validé' : 'Document électronique à corriger')
                            ->body($result['valid'] ? 'Le CII est conservé dans Cremona. Aucune facture n’a été transmise.' : 'Le rapport Super PDP est enregistré dans le journal de transmission. Aucune facture n’a été transmise.')
                            ->{$result['valid'] ? 'success' : 'warning'}()->send();
                    } catch (LogicException $exception) {
                        Notification::make()->title('Validation impossible')->body($exception->getMessage())->danger()->send();
                    } catch (Throwable) {
                        Notification::make()->title('Validation impossible')->body('Super PDP a renvoyé une erreur technique. La facture n’a pas été transmise.')->danger()->send();
                    }
                }),
            Action::make('downloadPdf')
                ->label('Télécharger le PDF')->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->record->pdfPrivateDocument !== null)
                ->url(fn (): string => route('private-documents.download', $this->record->pdfPrivateDocument?->public_id))
                ->openUrlInNewTab(),
            Action::make('submitToSuperPdp')
                ->label('Transmettre à Super PDP')->icon(Heroicon::OutlinedPaperAirplane)->color('danger')
                ->visible(fn (): bool => $this->record->transmissions()->where('provider', 'super_pdp')->where('status', 'validated')->exists())
                ->requiresConfirmation()
                ->modalHeading('Transmettre la facture à Super PDP')
                ->modalDescription('Cette action transmet réellement la facture électronique au réseau Super PDP. Elle est irréversible ; vérifiez la facture et son destinataire.')
                ->action(function (SuperPdpTransmissionManager $manager): void {
                    try {
                        $manager->submit($this->record);
                        $this->record->refresh();
                        Notification::make()->title('Facture remise à Super PDP')->body('La remise est enregistrée. Actualisez ensuite les statuts de traitement.')->success()->send();
                    } catch (LogicException $exception) {
                        Notification::make()->title('Transmission impossible')->body($exception->getMessage())->danger()->send();
                    } catch (Throwable) {
                        Notification::make()->title('Transmission à vérifier')->body('Le résultat peut être inconnu. Vérifiez Super PDP avant toute nouvelle tentative.')->danger()->send();
                    }
                }),
            Action::make('synchronizeSuperPdp')
                ->label('Actualiser les statuts Super PDP')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn (): bool => $this->record->transmissions()->where('provider', 'super_pdp')->whereIn('status', ['submitted', 'sent', 'rejected'])->exists())
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
