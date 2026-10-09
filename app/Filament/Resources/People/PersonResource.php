<?php

namespace App\Filament\Resources\People;

use App\Enums\ContactMethodType;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\RelationManagers\AppointmentsRelationManager;
use App\Filament\RelationManagers\ConversationsRelationManager;
use App\Filament\RelationManagers\NotesRelationManager;
use App\Filament\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Filament\Resources\People\Pages\ViewPerson;
use App\Filament\Resources\People\RelationManagers\CompaniesRelationManager;
use App\Filament\Resources\People\RelationManagers\IncomingRequestsRelationManager;
use App\Models\Person;
use App\Services\OrganizationPresentation;
use App\Support\ClientProfileOptions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PersonResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Suivi client';

    protected static ?string $navigationLabel = 'Contacts';

    protected static ?string $presentationKey = 'contacts';

    protected static string $organizationModule = 'crm';

    protected static ?string $presentationGroupKey = 'customer_follow_up';

    protected static ?string $modelLabel = 'contact';

    protected static ?string $pluralModelLabel = 'contacts';

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?int $navigationSort = 20;

    protected static bool $isGloballySearchable = true;

    protected static int $globalSearchResultsLimit = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'display_name',
            'first_name',
            'last_name',
            'contactMethods.value',
            'companies.name',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['contactMethods', 'companies']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Person $record */
        return array_filter([
            __('cremona.person.contact_details') => $record->contactMethods->pluck('value')->take(2)->implode(' · '),
            __('cremona.navigation.items.companies') => $record->companies->first()?->name,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make(__('cremona.person.identity'))
                    ->description('Les informations permettant d’identifier cette personne dans votre fichier clients.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)->schema([
                            TextInput::make('first_name')
                                ->label(__('cremona.request.first_name'))
                                ->maxLength(255)
                                ->columnSpan(6),
                            TextInput::make('last_name')
                                ->label(__('cremona.request.last_name'))
                                ->maxLength(255)
                                ->columnSpan(6),
                            TextInput::make('display_name')
                                ->label('Nom affiché')
                                ->helperText('Utilisé dans les listes et les documents. Laissez vide pour reprendre le prénom et le nom.')
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Coordonnées')
                    ->description('Ajoutez les moyens de joindre la personne et son adresse postale.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('contactMethods')
                            ->label('E-mail et téléphone')
                            ->relationship()
                            ->schema([
                                Select::make('type')
                                    ->label('Type')
                                    ->options(ContactMethodType::class)
                                    ->required()
                                    ->columnSpan(3),
                                TextInput::make('value')
                                    ->label('Coordonnée')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(5),
                                TextInput::make('label')
                                    ->label('Précision')
                                    ->placeholder('Ex. personnel ou atelier')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Toggle::make('is_primary')
                                    ->label('À privilégier')
                                    ->columnSpan(1),
                            ])
                            ->columns(12)
                            ->defaultItems(0)
                            ->addActionLabel('Ajouter une coordonnée')
                            ->columnSpanFull(),
                        Grid::make(12)->schema([
                            TextInput::make('address_line_1')->label('Adresse')->maxLength(255)->columnSpan(6),
                            TextInput::make('address_line_2')->label('Complément d’adresse')->maxLength(255)->columnSpan(6),
                            TextInput::make('postal_code')->label('Code postal')->maxLength(32)->columnSpan(3),
                            TextInput::make('city')->label('Ville')->maxLength(255)->columnSpan(5),
                            Select::make('country_code')->label('Pays')->options(ClientProfileOptions::countries())->default('FR')->searchable()->columnSpan(4),
                        ]),
                        Select::make('locale')
                            ->label('Langue de communication')
                            ->options(ClientProfileOptions::languages())
                            ->default('fr')
                            ->native(false)
                            ->columnSpan(4),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make(fn (): string => app(OrganizationPresentation::class)->label('contacts', 'Contact'))
                    ->columnSpan(8)
                    ->schema([
                        TextEntry::make('display_name')
                            ->label(__('common.display_name'))
                            ->weight('semibold')
                            ->size('lg'),
                        Grid::make(2)->schema([
                            TextEntry::make('first_name')->label(__('common.first_name'))->placeholder('—'),
                            TextEntry::make('last_name')->label(__('common.name'))->placeholder('—'),
                        ]),
                    ]),
                Section::make('Gestion interne')
                    ->columnSpan(4)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('common.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'active' ? __('common.active') : __('common.archived'))
                            ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                        TextEntry::make('source')->label('Origine')->formatStateUsing(fn (?string $state): string => ClientProfileOptions::sourceLabel($state)),
                        TextEntry::make('locale')->label('Langue de communication')->formatStateUsing(fn (?string $state): string => ClientProfileOptions::languageLabel($state)),
                        TextEntry::make('assignedUser.name')->label(__('common.assignee'))->placeholder(__('common.unassigned')),
                    ]),
                Section::make(__('common.contact_details'))
                    ->columnSpan(8)
                    ->schema([
                        RepeatableEntry::make('contactMethods')
                            ->label('')
                            ->schema([
                                TextEntry::make('type')->label(__('common.type'))->badge(),
                                TextEntry::make('value')->label(__('common.contact_detail'))->copyable()->weight('medium'),
                                TextEntry::make('label')->label(__('common.label'))->placeholder('—'),
                                IconEntry::make('is_primary')->label(__('common.primary'))->boolean(),
                            ])
                            ->columns(4),
                        TextEntry::make('address_line_1')->label('Adresse')->placeholder('—'),
                        TextEntry::make('address_line_2')->label('Complément d’adresse')->placeholder('—'),
                        Grid::make(3)->schema([
                            TextEntry::make('postal_code')->label('Code postal')->placeholder('—'),
                            TextEntry::make('city')->label('Ville')->placeholder('—'),
                            TextEntry::make('country_code')->label('Pays')->formatStateUsing(fn (?string $state): string => ClientProfileOptions::countryLabel($state)),
                        ]),
                    ]),
                Section::make('Activité')
                    ->columnSpan(4)
                    ->schema([
                        TextEntry::make('companies_count')
                            ->label(__('common.linked_companies'))
                            ->state(fn (Person $record): int => $record->companies()->count())
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('incoming_requests_count')
                            ->label(__('common.linked_requests'))
                            ->state(fn (Person $record): int => $record->incomingRequests()->count())
                            ->badge()
                            ->color('info'),
                        TextEntry::make('last_activity_at')
                            ->label(__('common.last_activity'))
                            ->since()
                            ->placeholder(__('common.no_activity')),
                        TextEntry::make('created_at')
                            ->label(__('common.created_at'))
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('display_name')
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('common.contact'))
                    ->searchable(['display_name', 'first_name', 'last_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('contactMethods.value')
                    ->label(__('common.contact_details'))
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable(),
                TextColumn::make('companies.name')
                    ->label(__('common.companies'))
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? __('common.active') : __('common.archived'))
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('updated_at')
                    ->label(__('common.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('common.status'))
                    ->options([
                        'active' => __('common.active'),
                        'archived' => __('common.archived'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            'requests' => IncomingRequestsRelationManager::class,
            'companies' => CompaniesRelationManager::class,
            'appointments' => AppointmentsRelationManager::class,
            'conversations' => ConversationsRelationManager::class,
            'notes' => NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'create' => CreatePerson::route('/create'),
            'view' => ViewPerson::route('/{record}'),
            'edit' => EditPerson::route('/{record}/edit'),
        ];
    }
}
