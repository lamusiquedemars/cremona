<?php

namespace App\Filament\Resources\Conversations;

use App\Enums\ConversationStatus;
use App\Filament\Resources\Conversations\Pages\ListConversations;
use App\Filament\Resources\Conversations\Pages\ViewConversation;
use App\Models\Conversation;
use App\Support\EmailReplyExcerpt;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?string $navigationLabel = 'Correspondances';

    protected static ?string $modelLabel = 'conversation';

    protected static ?string $pluralModelLabel = 'correspondances';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 30;

    public static function getNavigationBadge(): ?string
    {
        if (! DatabaseSchema::hasTable('conversations')) {
            return null;
        }

        $count = Conversation::query()->where('status', ConversationStatus::Open)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Conversation')->columnSpan(2)->schema([
                TextEntry::make('subject')->label('Objet')->placeholder('Sans objet')->weight('semibold'),
                TextEntry::make('person.display_name')->label('Contact')->placeholder('Non rattaché'),
                TextEntry::make('company.name')->label('Entreprise')->placeholder('—'),
                TextEntry::make('incomingRequest.subject')->label('Demande liée')->placeholder('—'),
            ]),
            Section::make('Suivi')->columnSpan(1)->schema([
                TextEntry::make('status')->label('Statut')->badge(),
                TextEntry::make('assignedUser.name')->label('Responsable')->placeholder('Non attribué'),
                TextEntry::make('last_message_at')->label('Dernier message')->dateTime('d/m/Y H:i')->placeholder('—'),
            ]),
            Section::make('Messages')->columnSpanFull()->schema([
                RepeatableEntry::make('messages')->label('')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('direction')
                            ->hiddenLabel()
                            ->formatStateUsing(fn ($state): string => $state->value === 'inbound' ? 'Message reçu' : 'Réponse envoyée')
                            ->color(fn ($state): string => $state->value === 'inbound' ? 'info' : 'success')
                            ->badge(),
                        TextEntry::make('authored_at')->hiddenLabel()->dateTime('d/m/Y H:i'),
                    ]),
                    TextEntry::make('subject')->hiddenLabel()->placeholder('Sans objet')->weight('semibold'),
                    TextEntry::make('body_text')
                        ->hiddenLabel()
                        ->formatStateUsing(function (string $state): HtmlString {
                            $parts = EmailReplyExcerpt::split($state);
                            $html = '<div class="whitespace-pre-wrap">'.e($parts['reply']).'</div>';

                            if ($parts['quoted'] !== null) {
                                $html .= '<details class="mt-3 text-sm text-gray-500">'
                                    .'<summary class="cursor-pointer">Afficher le contenu cité</summary>'
                                    .'<div class="mt-2 whitespace-pre-wrap border-l-2 border-gray-200 pl-3">'.e($parts['quoted']).'</div>'
                                    .'</details>';
                            }

                            return new HtmlString($html);
                        })
                        ->html()
                        ->columnSpanFull(),
                ])->contained(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('last_message_at', 'desc')->columns([
            TextColumn::make('subject')->label('Conversation')->placeholder('Sans objet')->searchable()->weight('medium'),
            TextColumn::make('person.display_name')->label('Contact')->placeholder('Non rattaché')->searchable(),
            TextColumn::make('status')->label('Statut')->badge()->sortable(),
            TextColumn::make('assignedUser.name')->label('Responsable')->placeholder('Non attribué'),
            TextColumn::make('last_message_at')->label('Dernier message')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->label('Statut')->options(ConversationStatus::class),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }
}
