<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Enums\QuoteStatus;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use App\Models\Quote;
use App\Services\QuoteDocumentProfileManager;
use App\Services\InvoiceFromQuoteManager;
use Filament\Notifications\Notification;
use LogicException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;

    protected string $view = 'filament.resources.quotes.pages.view-quote';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTitle(): string|Htmlable
    {
        return "Devis {$this->record->reference} — {$this->record->title}";
    }

    public function getBreadcrumb(): string
    {
        return $this->record->reference;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createInvoice')
                ->label('Créer la facture')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedDocumentCurrencyEuro)
                ->visible(fn (): bool => $this->record->status === QuoteStatus::Accepted && InvoiceResource::canAccess())
                ->action(function (InvoiceFromQuoteManager $manager): void {
                    try {
                        $invoice = $manager->createDraft($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Facture non créée')->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    $this->redirect(InvoiceResource::getUrl($invoice->status === InvoiceStatus::Draft ? 'edit' : 'view', ['record' => $invoice]));
                }),
            ActionGroup::make([
                Action::make('openWorkshopOrder')
                ->label(fn (): string => 'Dossier atelier '.$this->record->workshopOrder?->reference)
                ->visible(fn (): bool => $this->record->workshopOrder !== null && WorkshopOrderResource::canAccess())
                ->url(fn (): ?string => $this->record->workshopOrder ? WorkshopOrderResource::getUrl('edit', ['record' => $this->record->workshopOrder]) : null),
                Action::make('downloadPdf')
                ->label('Télécharger le PDF')
                ->url(fn (): string => route('quotes.pdf', ['publicId' => $this->record->public_id]))
                ->disabled(fn (): bool => count(app(QuoteDocumentProfileManager::class)->missingIssuerFields($this->record->organization)) > 0)
                ->tooltip(fn (): ?string => ($missing = app(QuoteDocumentProfileManager::class)->missingIssuerFields($this->record->organization)) === [] ? null : 'Complétez « Coordonnées et mentions légales » : '.implode(', ', $missing).'.')
                ->openUrlInNewTab(),
                EditAction::make()->label('Modifier le devis')
                ->visible(fn (): bool => $this->record->status === QuoteStatus::Draft),
                DeleteAction::make()->label('Supprimer le brouillon')
                ->visible(fn (): bool => $this->record->status === QuoteStatus::Draft),
            ])->label('Actions')->icon(\Filament\Support\Icons\Heroicon::OutlinedEllipsisHorizontal)->button()->color('gray'),
        ];
    }

    /** @return array<string, Quote> */
    protected function getViewData(): array
    {
        return [
            'quote' => $this->record->loadMissing([
                'organization',
                'person.contactMethods',
                'company.contactMethods',
                'lines',
            ]),
        ];
    }
}
