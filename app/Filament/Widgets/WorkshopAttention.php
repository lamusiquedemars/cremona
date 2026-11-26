<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Enums\WorkshopOrderStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use App\Filament\Resources\WorkshopOrders\WorkshopWorkflowActions;
use App\Models\WorkshopOrder;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class WorkshopAttention extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 34;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return WorkshopOrderResource::canAccess();
    }

    public function table(Table $table): Table
    {
        $now = now(app(OrganizationContext::class)->require()->timezone());
        $today = $now->toDateString();
        $tomorrow = $now->addDay()->toDateString();
        $quotesVisible = QuoteResource::canAccess();
        $billingVisible = $quotesVisible && InvoiceResource::canAccess();
        $followUpDate = "case status when 'received' then examination_on when 'diagnosed' then quote_follow_up_on
            when 'approved' then work_due_on when 'scheduled' then coalesce(work_due_on, due_at)
            when 'in_progress' then coalesce(work_due_on, due_at) end";

        return $table
            ->heading('À faire à l’atelier')
            ->query($this->attentionQuery($quotesVisible, $billingVisible))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw("($followUpDate) IS NULL, $followUpDate")->orderByDesc('updated_at'))
            ->columns([
                TextColumn::make('reference')->label('Dossier')->description(fn (WorkshopOrder $record): string => $record->title),
                TextColumn::make('person.display_name')->label('Client')->placeholder('—'),
                TextColumn::make('next_step')->label('À faire')->state(fn (WorkshopOrder $record): string => match ($record->status) {
                    WorkshopOrderStatus::Received => 'Préparer le devis',
                    WorkshopOrderStatus::Diagnosed => $record->quote === null ? 'Préparer le devis' : 'Contacter le client au sujet du devis',
                    WorkshopOrderStatus::Ready, WorkshopOrderStatus::Returned => $this->billingStep($record, $billingVisible),
                    default => 'Réaliser les travaux',
                })->wrap(),
                TextColumn::make('follow_up_date')->label('Rappel ou échéance')
                    ->state(fn (WorkshopOrder $record): ?string => $record->followUpDate()?->format('d/m/Y'))
                    ->description(fn (WorkshopOrder $record): ?string => match (true) {
                        $record->followUpDate() === null => null,
                        $record->followUpDate()->toDateString() < $today => 'En retard',
                        $record->followUpDate()->toDateString() === $today => 'Aujourd’hui',
                        $record->followUpDate()->toDateString() === $tomorrow => 'Demain',
                        default => null,
                    })
                    ->color(fn (WorkshopOrder $record): string => match (true) {
                        $record->followUpDate() === null => 'gray',
                        $record->followUpDate()->toDateString() < $today => 'danger',
                        $record->followUpDate()->toDateString() <= $tomorrow => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—'),
            ])
            ->recordActions([
                ...WorkshopWorkflowActions::primary(),
                ActionGroup::make(WorkshopWorkflowActions::secondary())->label('Autres actions'),
            ])
            ->recordUrl(fn (WorkshopOrder $record): string => WorkshopOrderResource::getUrl('view', ['record' => $record]))
            ->headerActions([Action::make('seeWorkOrders')->label('Voir les dossiers atelier')->url(WorkshopOrderResource::getUrl('index'))])
            ->emptyStateHeading('Aucun dossier atelier à traiter')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    private function attentionQuery(bool $quotesVisible, bool $billingVisible): Builder
    {
        return WorkshopOrder::query()->with(['person', 'quote.invoice'])->where(function (Builder $query) use ($quotesVisible, $billingVisible): void {
            $query->whereIn('status', [WorkshopOrderStatus::Received, WorkshopOrderStatus::Approved, WorkshopOrderStatus::Scheduled, WorkshopOrderStatus::InProgress, WorkshopOrderStatus::Ready]);

            if ($quotesVisible) {
                $query->orWhere('status', WorkshopOrderStatus::Diagnosed);
            }

            if ($billingVisible) {
                $query->orWhere(fn (Builder $returned): Builder => $returned
                    ->where('status', WorkshopOrderStatus::Returned)
                    ->whereHas('quote', fn (Builder $quote): Builder => $quote->where('status', QuoteStatus::Accepted)
                        ->whereDoesntHave('invoice', fn (Builder $invoice): Builder => $invoice->where('status', '!=', InvoiceStatus::Draft))));
            }
        });
    }

    private function billingStep(WorkshopOrder $record, bool $billingVisible): string
    {
        if ($billingVisible && $record->quote?->status === QuoteStatus::Accepted) {
            if ($record->quote->invoice === null) {
                return 'À facturer';
            }

            if ($record->quote->invoice->status === InvoiceStatus::Draft) {
                return 'Vérifier et émettre la facture';
            }
        }

        return 'Restituer l’instrument';
    }
}
