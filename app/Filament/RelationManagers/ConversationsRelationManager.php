<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConversationsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'conversations';

    protected static ?string $title = 'Correspondances';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedChatBubbleLeftRight;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('subject')->label('Conversation')->placeholder('Sans objet')->weight('medium'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('assignedUser.name')->label('Responsable')->placeholder('Non attribué'),
                TextColumn::make('last_message_at')->label('Dernier message')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make()->url(fn (Conversation $record): string => ConversationResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Conversation $record): string => ConversationResource::getUrl('view', ['record' => $record]));
    }
}
