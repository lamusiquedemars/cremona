<?php

namespace App\Filament\Resources\Conversations;

use App\Enums\ConversationStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageParticipantRole;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\Conversations\Pages\ListConversations;
use App\Filament\Resources\Conversations\Pages\ViewConversation;
use App\Models\Conversation;
use App\Services\CorrespondenceManager;
use App\Services\OrganizationPresentation;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use UnitEnum;

class ConversationResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Suivi client';

    protected static ?string $navigationLabel = 'Correspondances';

    protected static ?string $presentationKey = 'conversations';

    protected static string $organizationModule = 'crm';

    protected static ?string $presentationGroupKey = 'customer_follow_up';

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
            Section::make()->columnSpanFull()->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextEntry::make('initial_channel')
                        ->label('Origine')
                        ->badge()
                        ->formatStateUsing(fn (MessageChannel|string $state): string => self::originLabel($state)),
                    TextEntry::make('status')->label(__('cremona.crm.status'))->badge(),
                ]),
                ViewEntry::make('conversation_timeline')
                    ->hiddenLabel()
                    ->state(fn (Conversation $record) => $record->messages()
                        ->with(['participants', 'mailbox'])
                        ->orderBy('authored_at')
                        ->get()
                        ->values())
                    ->view('filament.infolists.conversation-timeline')
                    ->columnSpanFull(),
            ]),
            Section::make('Informations liées')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('person.display_name')->label(fn (): string => app(OrganizationPresentation::class)->label('contacts', 'Contact'))->placeholder(__('cremona.crm.unlinked')),
                TextEntry::make('company.name')->label(fn (): string => app(OrganizationPresentation::class)->label('companies', 'Entreprise'))->placeholder('—'),
                TextEntry::make('incomingRequest.subject')->label(fn (): string => app(OrganizationPresentation::class)->label('requests', 'Demande'))->placeholder('—'),
            ]),
            Section::make(__('cremona.crm.follow_up'))->columnSpan(1)->schema([
                TextEntry::make('assignedUser.name')->label(__('cremona.crm.assignee'))->placeholder(__('cremona.crm.unassigned')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'correspondence-table'])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('latestInboundMessage.participants'))
            ->defaultSort('last_message_at', 'desc')
            ->columns([
            TextColumn::make('subject')
                ->label(__('cremona.crm.subject'))
                ->placeholder(__('cremona.crm.untitled'))
                ->searchable()
                ->weight('medium')
                ->limit(96)
                ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                ->wrap()
                ->lineClamp(2)
                ->width('27rem')
                ->grow(false)
                ->sortable(),
            TextColumn::make('sender')
                ->label('Expéditeur')
                ->state(fn (Conversation $record): ?string => self::senderName($record))
                ->description(fn (Conversation $record): ?string => self::senderAddress($record))
                ->placeholder(__('cremona.dashboard.anonymous'))
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('messages.participants', fn (Builder $query): Builder => $query->where('address', 'like', "%{$search}%")))
                ->weight('medium'),
            TextColumn::make('initial_channel')
                ->label('Origine')
                ->badge()
                ->formatStateUsing(fn (MessageChannel|string $state): string => self::originLabel($state))
                ->color(fn (MessageChannel|string $state): string => self::originColor($state))
                ->toggleable()
                ->sortable(),
            TextColumn::make('status')->label(__('cremona.crm.status'))->badge()->toggleable()->sortable(),
            TextColumn::make('assignedUser.name')->label(__('cremona.crm.assignee'))->placeholder(__('cremona.crm.unassigned'))->toggleable(isToggledHiddenByDefault: true)->sortable(),
            TextColumn::make('last_message_at')
                ->label(__('cremona.crm.last_message'))
                ->dateTime('d/m/Y H:i')
                ->toggleable()
                ->sortable(),
        ])->filters([
            SelectFilter::make('status')->label(__('cremona.crm.status'))->options(ConversationStatus::class),
            SelectFilter::make('initial_channel')
                ->label('Origine')
                ->options([
                    MessageChannel::Website->value => 'Formulaire du site',
                    MessageChannel::Email->value => 'E-mail direct',
                ]),
        ])->bulkActions([
            BulkActionGroup::make([
                BulkAction::make('markRead')
                    ->label(__('cremona.crm.mark_as_read'))
                    ->icon(Heroicon::OutlinedEnvelopeOpen)
                    ->action(function (Collection $records): void {
                        $manager = app(CorrespondenceManager::class);

                        $records->each(function (Conversation $record) use ($manager): void {
                            Gate::authorize('view', $record);
                            $manager->markRead($record, auth()->user());
                        });
                    }),
                BulkAction::make('close')
                    ->label(__('cremona.crm.close'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Clore les correspondances sélectionnées ?')
                    ->modalDescription('Elles seront conservées et resteront visibles dans l’onglet des correspondances clôturées.')
                    ->action(function (Collection $records): void {
                        $manager = app(CorrespondenceManager::class);

                        $records->each(function (Conversation $record) use ($manager): void {
                            Gate::authorize('update', $record);
                            $manager->closeConversation($record, auth()->user());
                        });
                    }),
            ]),
        ])
            ->recordUrl(fn (Conversation $record): string => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }

    private static function originLabel(MessageChannel|string $state): string
    {
        return ($state instanceof MessageChannel ? $state : MessageChannel::from($state)) === MessageChannel::Website
            ? 'Formulaire du site'
            : 'E-mail direct';
    }

    private static function originColor(MessageChannel|string $state): string
    {
        return ($state instanceof MessageChannel ? $state : MessageChannel::from($state)) === MessageChannel::Website
            ? 'info'
            : 'gray';
    }

    private static function senderName(Conversation $record): ?string
    {
        $sender = $record->latestInboundMessage?->participants
            ->first(fn ($participant): bool => $participant->role === MessageParticipantRole::From);

        return $sender?->name ?: $sender?->address;
    }

    private static function senderAddress(Conversation $record): ?string
    {
        $sender = $record->latestInboundMessage?->participants
            ->first(fn ($participant): bool => $participant->role === MessageParticipantRole::From);

        return $sender?->name && $sender->address && $sender->name !== $sender->address
            ? $sender->address
            : null;
    }
}
