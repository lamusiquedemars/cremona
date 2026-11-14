<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use App\Tenancy\OrganizationContext;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    public function getTabs(): array
    {
        $today = now(app(OrganizationContext::class)->require()->timezone())->toDateString();

        return [
            'all' => Tab::make('Tous'),
            'follow_up' => Tab::make('À suivre')
                ->badge(fn (): int => Quote::query()->where('status', QuoteStatus::Sent)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', QuoteStatus::Sent)),
            'expired' => Tab::make('Hors validité')
                ->badge(fn (): int => Quote::query()
                    ->where('status', QuoteStatus::Sent)
                    ->whereDate('valid_until', '<', $today)
                    ->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', QuoteStatus::Sent)
                    ->whereDate('valid_until', '<', $today)),
            'draft' => Tab::make('Brouillons')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', QuoteStatus::Draft)),
            'accepted' => Tab::make('Acceptés')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', QuoteStatus::Accepted)),
        ];
    }
}
