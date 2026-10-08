<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Enums\RentalAcceptanceStatus;
use App\Enums\RentalStatus;
use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Rental;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRentals extends ListRecords
{
    protected static string $resource = RentalResource::class;

    public function getTabs(): array
    {
        $returnHorizon = today()->addDays(14)->toDateString();

        return [
            'all' => Tab::make('Toutes'),
            'attention' => Tab::make('À traiter')
                ->badge(fn (): int => $this->attentionQuery($returnHorizon)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyAttentionQuery($query, $returnHorizon)),
            'awaiting_acceptance' => Tab::make('Acceptation client')
                ->badge(fn (): int => Rental::query()->whereHas('latestAcceptance', fn (Builder $query): Builder => $query->whereIn('status', [RentalAcceptanceStatus::Created, RentalAcceptanceStatus::Sent]))->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas('latestAcceptance', fn (Builder $query): Builder => $query->whereIn('status', [RentalAcceptanceStatus::Created, RentalAcceptanceStatus::Sent]))),
            'ready' => Tab::make('À remettre')
                ->badge(fn (): int => Rental::query()->where('status', RentalStatus::Draft)->whereHas('latestAcceptance', fn (Builder $query): Builder => $query->where('status', RentalAcceptanceStatus::Accepted))->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RentalStatus::Draft)->whereHas('latestAcceptance', fn (Builder $query): Builder => $query->where('status', RentalAcceptanceStatus::Accepted))),
            'active' => Tab::make('En cours')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RentalStatus::Active)),
            'returned' => Tab::make('Restituées')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RentalStatus::Returned)),
        ];
    }

    private function attentionQuery(string $returnHorizon): Builder
    {
        return $this->applyAttentionQuery(Rental::query(), $returnHorizon);
    }

    private function applyAttentionQuery(Builder $query, string $returnHorizon): Builder
    {
        return $query->where(function (Builder $query) use ($returnHorizon): void {
            $query->where('status', RentalStatus::Draft)
                ->orWhere(fn (Builder $query): Builder => $query->where('status', RentalStatus::Active)->whereDate('expected_return_on', '<=', $returnHorizon))
                ->orWhereHas('latestAcceptance', fn (Builder $query): Builder => $query->whereIn('status', [RentalAcceptanceStatus::Created, RentalAcceptanceStatus::Sent]));
        });
    }
}
