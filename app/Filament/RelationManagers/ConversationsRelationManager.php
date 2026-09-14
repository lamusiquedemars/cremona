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

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('common.conversations');
    }

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedChatBubbleLeftRight;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('subject')->label(__('common.conversation'))->placeholder(__('common.untitled'))->weight('medium'),
                TextColumn::make('status')->label(__('common.status'))->badge(),
                TextColumn::make('assignedUser.name')->label(__('common.assignee'))->placeholder(__('common.unassigned')),
                TextColumn::make('last_message_at')->label(__('common.last_message'))->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make()->url(fn (Conversation $record): string => ConversationResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Conversation $record): string => ConversationResource::getUrl('view', ['record' => $record]));
    }
}
