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
            'sent' => Tab::make('Dossiers envoyés')
                ->badge(fn (): int => Rental::query()->where('status', RentalStatus::Sent)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RentalStatus::Sent)),
            'accepted' => Tab::make('Instruments à remettre')
                ->badge(fn (): int => Rental::query()->where('status', RentalStatus::Accepted)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RentalStatus::Accepted)),
            'active' => Tab::make('Instruments remis')->badge(fn (): int => Rental::query()->where('status', RentalStatus::HandedOver)->count())->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RentalStatus::HandedOver)),
            'returned' => Tab::make('Instruments restitués')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RentalStatus::Returned)),
        ];
    }

    private function attentionQuery(string $returnHorizon): Builder
    {
        return $this->applyAttentionQuery(Rental::query(), $returnHorizon);
    }

    private function applyAttentionQuery(Builder $query, string $returnHorizon): Builder
    {
        return $query->where(function (Builder $query) use ($returnHorizon): void {
            $query->whereIn('status', [RentalStatus::Preparing, RentalStatus::Sent, RentalStatus::Accepted])
                ->orWhere(fn (Builder $query): Builder => $query->where('status', RentalStatus::HandedOver)->whereDate('expected_return_on', '<=', $returnHorizon))
                ->orWhereIn('collection_status', ['to_set_up', 'to_stop']);
        });
    }
}
