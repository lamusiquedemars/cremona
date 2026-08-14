<?php

namespace App\Filament\Resources\IncomingRequests;

use App\Enums\IncomingRequestStatus;
use App\Enums\IncomingRequestUrgency;
use App\Filament\Resources\IncomingRequests\Pages\ListIncomingRequests;
use App\Filament\Resources\IncomingRequests\Pages\ViewIncomingRequest;
use App\Models\IncomingRequest;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class IncomingRequestResource extends Resource
{
    protected static ?string $model = IncomingRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?string $navigationLabel = 'Demandes';

    protected static ?string $modelLabel = 'demande';

    protected static ?string $pluralModelLabel = 'demandes';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', IncomingRequestStatus::New)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'info';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Demande reçue')
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('subject')
                            ->label('Objet')
                            ->placeholder('Sans objet')
                            ->weight('semibold'),
                        TextEntry::make('message')
                            ->label('Message')
                            ->columnSpanFull(),
                    ]),
                Section::make('Traitement')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),
                        TextEntry::make('urgency')
                            ->label('Urgence')
                            ->badge(),
                        TextEntry::make('assignedUser.name')
                            ->label('Responsable')
                            ->placeholder('Non attribuée'),
                        TextEntry::make('outcome')
                            ->label('Résultat')
                            ->badge()
                            ->placeholder('—'),
                    ]),
                Section::make('Contact déclaré')
                    ->description('Ces données restent le reflet exact du formulaire reçu, même après rattachement à une fiche CRM.')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('name_snapshot')->label('Nom')->placeholder('—'),
                            TextEntry::make('email_snapshot')->label('E-mail')->placeholder('—')->copyable(),
                            TextEntry::make('phone_snapshot')->label('Téléphone')->placeholder('—')->copyable(),
                            TextEntry::make('important_date')->label('Date importante')->date()->placeholder('—'),
                        ]),
                    ]),
                Section::make('Rattachements CRM')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('person.display_name')
                            ->label('Contact')
                            ->placeholder('Non rattaché'),
                        TextEntry::make('company.name')
                            ->label('Entreprise')
                            ->placeholder('Non rattachée'),
                        TextEntry::make('source')
                            ->label('Origine')
                            ->placeholder('—'),
                        TextEntry::make('source_channel')
                            ->label('Canal')
                            ->badge(),
                    ]),
                Section::make('Réponses complémentaires')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(fn (IncomingRequest $record): bool => $record->answers->isEmpty())
                    ->schema([
                        RepeatableEntry::make('answers')
                            ->label('')
                            ->schema([
                                TextEntry::make('label_snapshot')->label('Question')->weight('medium'),
                                TextEntry::make('value')->label('Réponse')->placeholder('—'),
                            ])
                            ->columns(2),
                    ]),
                Section::make('Consentements')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(fn (IncomingRequest $record): bool => $record->consents->isEmpty())
                    ->schema([
                        RepeatableEntry::make('consents')
                            ->label('')
                            ->schema([
                                TextEntry::make('purpose')->label('Finalité'),
                                TextEntry::make('channel')->label('Canal'),
                                TextEntry::make('status')->label('Statut')->badge(),
                                TextEntry::make('statement_snapshot')->label('Texte présenté')->columnSpanFull(),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Historique')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('activities')
                            ->label('')
                            ->schema([
                                TextEntry::make('event')
                                    ->label('Événement')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'received' => 'Demande reçue',
                                        'read' => 'Marquée comme lue',
                                        'status_changed' => 'Statut modifié',
                                        'assigned' => 'Responsable attribué',
                                        'person_linked' => 'Contact rattaché',
                                        'company_linked' => 'Entreprise rattachée',
                                        'note_added' => 'Note ajoutée',
                                        default => $state,
                                    })
                                    ->badge(),
                                TextEntry::make('actor.name')->label('Par')->placeholder('Système'),
                                TextEntry::make('body')->label('Détail')->placeholder('—'),
                                TextEntry::make('recorded_at')->label('Date')->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('name_snapshot')
                    ->label('Contact')
                    ->description(fn (IncomingRequest $record): ?string => $record->email_snapshot ?? $record->phone_snapshot)
                    ->placeholder('Anonyme')
                    ->searchable(['name_snapshot', 'email_snapshot', 'phone_snapshot'])
                    ->weight('medium'),
                TextColumn::make('subject')
                    ->label('Demande')
                    ->description(fn (IncomingRequest $record): string => str($record->message)->squish()->limit(70))
                    ->placeholder('Sans objet')
                    ->searchable(['subject', 'message'])
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),
                TextColumn::make('urgency')
                    ->label('Urgence')
                    ->badge()
                    ->sortable(),
                TextColumn::make('assignedUser.name')
                    ->label('Responsable')
                    ->placeholder('Non attribuée')
                    ->toggleable(),
                TextColumn::make('received_at')
                    ->label('Reçue')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(IncomingRequestStatus::class),
                SelectFilter::make('urgency')
                    ->label('Urgence')
                    ->options(IncomingRequestUrgency::class),
                Filter::make('unread')
                    ->label('Non lues')
                    ->query(fn (Builder $query): Builder => $query->whereNull('read_at')),
                Filter::make('unassigned')
                    ->label('Non attribuées')
                    ->query(fn (Builder $query): Builder => $query->whereNull('assigned_user_id')),
            ])
            ->recordClasses(fn (IncomingRequest $record): ?string => $record->read_at === null ? 'crm-record-unread' : null)
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncomingRequests::route('/'),
            'view' => ViewIncomingRequest::route('/{record}'),
        ];
    }
}
