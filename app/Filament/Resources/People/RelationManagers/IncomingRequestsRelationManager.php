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

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedInboxStack;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->label('Demande')
                    ->description(fn (IncomingRequest $record): string => str($record->message)->squish()->limit(70))
                    ->placeholder('Sans objet')
                    ->wrap(),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('urgency')->label('Urgence')->badge(),
                TextColumn::make('assignedUser.name')->label('Responsable')->placeholder('Non attribuée'),
                TextColumn::make('received_at')->label('Reçue')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (IncomingRequest $record): string => IncomingRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (IncomingRequest $record): string => IncomingRequestResource::getUrl('view', ['record' => $record]));
    }
}
