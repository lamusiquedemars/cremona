<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Enums\ConversationStatus;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toutes'),
            'open' => Tab::make('À traiter')
                ->badge(fn (): int => $this->count(ConversationStatus::Open))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ConversationStatus::Open)),
            'waiting' => Tab::make('En attente du client')
                ->badge(fn (): int => $this->count(ConversationStatus::WaitingCustomer))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ConversationStatus::WaitingCustomer)),
            'closed' => Tab::make('Clôturées')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ConversationStatus::Closed)),
        ];
    }

    private function count(ConversationStatus $status): int
    {
        return Conversation::query()->where('status', $status)->count();
    }
}
