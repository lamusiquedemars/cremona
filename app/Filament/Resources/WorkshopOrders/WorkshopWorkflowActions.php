<?php

namespace App\Filament\Resources\WorkshopOrders;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Enums\WorkshopOrderStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\WorkshopOrder;
use App\Services\InvoiceFromQuoteManager;
use App\Services\QuoteDocumentProfileManager;
use App\Services\QuoteWorkflowManager;
use App\Services\WorkshopOrderWorkflowManager;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use LogicException;

class WorkshopWorkflowActions
{
    /** @return list<Action> */
    public static function primary(): array
    {
        return [
            self::run(
                Action::make('prepareWorkshopQuote')
                    ->label('Préparer le devis')
                    ->icon(Heroicon::OutlinedDocumentCurrencyEuro)
                    ->visible(fn (WorkshopOrder $record): bool => ($record->status === WorkshopOrderStatus::Received
                            && ($record->quote === null || $record->quote->status === QuoteStatus::Draft))
                        || ($record->status === WorkshopOrderStatus::Diagnosed && $record->quote === null)),
                function (WorkshopOrder $record) {
                    $quote = app(WorkshopOrderWorkflowManager::class)->prepareQuote($record);

                    return redirect()->to(QuoteResource::getUrl('edit', ['record' => $quote]));
                },
                fn (WorkshopOrder $record): bool => $record->quote === null ? QuoteResource::canCreate() : self::canEditQuote($record),
            ),
            self::run(
                Action::make('sendWorkshopQuote')
                    ->label('Envoyer le devis')
                    ->visible(fn (WorkshopOrder $record): bool => $record->status === WorkshopOrderStatus::Diagnosed && $record->quote?->status === QuoteStatus::Draft)
                    ->modalDescription(fn (WorkshopOrder $record): string => self::contactDescription($record))
                    ->schema([
                        Select::make('sent_via')->label('Mode de transmission')->options([
                            'email' => 'E-mail',
                            'in_person' => 'Remis au client',
                            'other' => 'Autre canal',
                        ])->default('email')->live()->required(),
                        TextInput::make('recipient_email')
                            ->label('Adresse e-mail du client')
                            ->email()
                            ->default(fn (WorkshopOrder $record): ?string => self::recipientEmail($record))
                            ->visible(fn (Get $get): bool => $get('sent_via') === 'email')
                            ->required(fn (Get $get): bool => $get('sent_via') === 'email')
                            ->helperText('Adresse préremplie lorsqu’elle existe dans la fiche client. Vous pouvez la modifier pour cet envoi ; le PDF du devis sera joint au message.'),
                    ]),
                fn (WorkshopOrder $record, array $data) => $data['sent_via'] === 'email'
                    ? app(QuoteWorkflowManager::class)->sendByEmail($record->quote, $data['recipient_email'])
                    : app(QuoteWorkflowManager::class)->markSent($record->quote, $data['sent_via']),
                self::canEditQuote(...),
            ),
            self::run(
                Action::make('acceptWorkshopQuote')
                    ->label('Enregistrer l’accord')
                    ->visible(fn (WorkshopOrder $record): bool => $record->quote?->status === QuoteStatus::Sent)
                    ->requiresConfirmation(),
                fn (WorkshopOrder $record) => app(QuoteWorkflowManager::class)->markAccepted($record->quote),
                self::canEditQuote(...),
            ),
            self::run(
                Action::make('completeWorkshopWork')
                    ->label('Marquer le travail réalisé')
                    ->visible(fn (WorkshopOrder $record): bool => self::workCanBeCompleted($record))
                    ->requiresConfirmation(),
                fn (WorkshopOrder $record) => app(WorkshopOrderWorkflowManager::class)->markReady($record),
            ),
            self::run(
                Action::make('prepareWorkshopInvoice')
                    ->label('Préparer la facture')
                    ->visible(fn (WorkshopOrder $record): bool => self::canPrepareInvoice($record)),
                function (WorkshopOrder $record) {
                    $invoice = app(InvoiceFromQuoteManager::class)->createDraft($record->quote);

                    return redirect()->to(InvoiceResource::getUrl($invoice->status === InvoiceStatus::Draft ? 'edit' : 'view', ['record' => $invoice]));
                },
                fn (WorkshopOrder $record): bool => QuoteResource::canAccess()
                    && $record->quote?->status === QuoteStatus::Accepted
                    && InvoiceResource::canCreate(),
            ),
            Action::make('openWorkshopInvoice')
                ->label(fn (WorkshopOrder $record): string => $record->quote?->invoice?->status === InvoiceStatus::Draft ? 'Ouvrir la facture brouillon' : 'Voir la facture')
                ->visible(fn (WorkshopOrder $record): bool => InvoiceResource::canAccess()
                    && $record->quote?->invoice !== null
                    && InvoiceResource::canView($record->quote->invoice))
                ->url(function (WorkshopOrder $record): string {
                    $invoice = $record->quote->invoice;

                    return InvoiceResource::getUrl($invoice->status === InvoiceStatus::Draft && InvoiceResource::canEdit($invoice) ? 'edit' : 'view', ['record' => $invoice]);
                }),
        ];
    }

    /** @return list<Action> */
    public static function secondary(): array
    {
        return [
            self::dateAction('examination_on', 'Ajouter un rappel', 'Date du rappel', fn (WorkshopOrder $record): bool => $record->status === WorkshopOrderStatus::Received),
            self::dateAction('quote_follow_up_on', 'Prévoir le contact client', 'Date de rappel', fn (WorkshopOrder $record): bool => $record->status === WorkshopOrderStatus::Diagnosed && $record->quote?->status === QuoteStatus::Draft, self::canEditQuote(...)),
            self::dateAction('work_due_on', 'Fixer l’échéance des travaux', 'À terminer pour le', self::workCanBeCompleted(...)),
            Action::make('viewWorkshopQuote')
                ->label(fn (WorkshopOrder $record): string => 'Ouvrir le devis '.$record->quote?->reference)
                ->visible(fn (WorkshopOrder $record): bool => self::canViewQuote($record))
                ->url(fn (WorkshopOrder $record): string => QuoteResource::getUrl('view', ['record' => $record->quote])),
            Action::make('downloadWorkshopQuote')
                ->label('Télécharger le devis PDF')
                ->visible(fn (WorkshopOrder $record): bool => self::canViewQuote($record) && $record->quote->status === QuoteStatus::Draft)
                ->disabled(fn (WorkshopOrder $record): bool => app(QuoteDocumentProfileManager::class)->missingIssuerFields($record->organization) !== [])
                ->url(fn (WorkshopOrder $record): string => route('quotes.pdf', ['publicId' => $record->quote->public_id]))
                ->openUrlInNewTab(),
            self::run(
                Action::make('declineWorkshopQuote')
                    ->label('Enregistrer le refus')
                    ->visible(fn (WorkshopOrder $record): bool => $record->quote?->status === QuoteStatus::Sent)
                    ->requiresConfirmation(),
                fn (WorkshopOrder $record) => app(QuoteWorkflowManager::class)->markDeclined($record->quote),
                self::canEditQuote(...),
            ),
            self::run(
                Action::make('returnWorkshopInstrument')
                    ->label('Enregistrer la restitution')
                    ->visible(fn (WorkshopOrder $record): bool => $record->status === WorkshopOrderStatus::Ready)
                    ->requiresConfirmation(),
                fn (WorkshopOrder $record) => app(WorkshopOrderWorkflowManager::class)->markReturned($record),
            ),
        ];
    }

    private static function dateAction(string $field, string $label, string $dateLabel, Closure $visible, ?Closure $allowed = null): Action
    {
        return self::run(
            Action::make($field)
                ->label($label)
                ->icon(Heroicon::OutlinedCalendarDays)
                ->visible($visible)
                ->fillForm(fn (WorkshopOrder $record): array => ['date' => $record->{$field}?->toDateString()])
                ->schema([DatePicker::make('date')->label($dateLabel)]),
            fn (WorkshopOrder $record, array $data) => app(WorkshopOrderWorkflowManager::class)->setFollowUpDate($record, $field, $data['date'] ?? null),
            $allowed,
        );
    }

    private static function run(Action $action, Closure $run, ?Closure $allowed = null): Action
    {
        $authorized = fn (WorkshopOrder $record): bool => WorkshopOrderResource::canEdit($record)
            && ($allowed === null || $allowed($record));

        return $action
            ->authorize($authorized)
            ->action(function (WorkshopOrder $record, array $data) use ($authorized, $run): mixed {
                abort_unless($authorized($record), 403);

                try {
                    $result = $run($record, $data);
                } catch (LogicException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return null;
                }

                $record->refresh();
                Notification::make()->title('Dossier mis à jour')->success()->send();

                return $result;
            });
    }

    private static function canViewQuote(WorkshopOrder $record): bool
    {
        return QuoteResource::canAccess() && $record->quote !== null && QuoteResource::canView($record->quote);
    }

    private static function canEditQuote(WorkshopOrder $record): bool
    {
        return $record->quote !== null && QuoteResource::canEdit($record->quote);
    }

    private static function canPrepareInvoice(WorkshopOrder $record): bool
    {
        return QuoteResource::canAccess()
            && in_array($record->status, [WorkshopOrderStatus::Ready, WorkshopOrderStatus::Returned], true)
            && $record->quote?->status === QuoteStatus::Accepted
            && $record->quote->invoice === null;
    }

    private static function workCanBeCompleted(WorkshopOrder $record): bool
    {
        return in_array($record->status, [WorkshopOrderStatus::Approved, WorkshopOrderStatus::Scheduled, WorkshopOrderStatus::InProgress], true);
    }

    private static function contactDescription(WorkshopOrder $record): string
    {
        $contact = $record->quote->person ?? $record->quote->company ?? $record->person;
        $details = $contact?->contactMethods->map(fn ($method): string => $method->type->getLabel().' '.$method->value)->all() ?? [];
        $recipient = $contact === null ? 'Client à renseigner' : implode(' · ', [$contact->display_name ?? $contact->name, ...$details]);

        return $recipient.' — Confirmez après avoir transmis le devis.';
    }

    private static function recipientEmail(WorkshopOrder $record): ?string
    {
        return $record->quote?->person?->contactMethods->firstWhere('type', 'email')?->value
            ?? $record->quote?->company?->contactMethods->firstWhere('type', 'email')?->value
            ?? $record->person?->contactMethods->firstWhere('type', 'email')?->value;
    }
}
