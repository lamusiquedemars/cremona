<?php

namespace App\Filament\Resources\People\RelationManagers;

use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\IncomingRequest;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IncomingRequestsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'incomingRequests';

    protected static ?string $title = 'Demandes';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('common.requests');
    }

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedInboxStack;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->label(__('common.request'))
                    ->description(fn (IncomingRequest $record): string => str($record->message)->squish()->limit(70))
                    ->placeholder(__('common.untitled'))
                    ->wrap(),
                TextColumn::make('status')->label(__('common.status'))->badge(),
                TextColumn::make('urgency')->label(__('common.urgency'))->badge(),
                TextColumn::make('assignedUser.name')->label(__('common.assignee'))->placeholder(__('common.unassigned')),
                TextColumn::make('received_at')->label(__('common.received'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (IncomingRequest $record): string => IncomingRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (IncomingRequest $record): string => IncomingRequestResource::getUrl('view', ['record' => $record]));
    }
}
