<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\InvoiceWorkflowManager;
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

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;
    protected Width|string|null $maxContentWidth = Width::Full;

    protected array $extraBodyAttributes = [
        'class' => 'cremona-record-view',
    ];

    public function getTitle(): string|Htmlable { return ($this->record->reference ?: 'Brouillon').' — '.$this->record->title; }
    public function getBreadcrumb(): string { return $this->record->reference ?: 'Brouillon'; }

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
                Action::make('downloadPdf')
                ->label('Télécharger le PDF')->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->record->pdfPrivateDocument !== null)
                ->url(fn (): string => route('private-documents.download', $this->record->pdfPrivateDocument?->public_id))
                ->openUrlInNewTab(),
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
