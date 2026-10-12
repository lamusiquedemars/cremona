<?php

namespace App\Filament\Resources\People;

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
use App\Support\ClientProfileOptions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

    protected static ?string $navigationLabel = 'Clients particuliers';

    protected static ?string $presentationKey = 'contacts';

    protected static string $organizationModule = 'crm';

    protected static ?string $presentationGroupKey = 'customer_follow_up';

    protected static ?string $modelLabel = 'client particulier';

    protected static ?string $pluralModelLabel = 'clients particuliers';

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
                    ->description('Les moyens de joindre le client et son adresse postale.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)->schema([
                            TextInput::make('contact_preview_email')
                                ->label('E-mail')
                                ->placeholder('prenom.nom@example.fr')
                                ->email()
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(6),
                            TextInput::make('contact_preview_phone')
                                ->label('Téléphone')
                                ->placeholder('06 12 34 56 78')
                                ->tel()
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(6),
                            TextInput::make('contact_preview_address_line_1')
                                ->label('Adresse')
                                ->placeholder('12 rue des Luthiers')
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(8),
                            TextInput::make('contact_preview_address_line_2')
                                ->label('Complément d’adresse')
                                ->placeholder('Bâtiment, étage, lieu-dit…')
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(4),
                            TextInput::make('contact_preview_postal_code')
                                ->label('Code postal')
                                ->placeholder('69001')
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(3),
                            TextInput::make('contact_preview_city')
                                ->label('Ville')
                                ->placeholder('Lyon')
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(5),
                            Select::make('contact_preview_country')
                                ->label('Pays')
                                ->options(ClientProfileOptions::countries())
                                ->default('FR')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(4),
                        ]),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make('Informations client')
                    ->columnSpan(8)
                    ->schema([
                        Grid::make(12)->schema([
                            TextEntry::make('first_name')
                                ->label(__('common.first_name'))
                                ->placeholder('—')
                                ->columnSpan(6),
                            TextEntry::make('last_name')
                                ->label(__('common.name'))
                                ->placeholder('—')
                                ->columnSpan(6),
                            TextEntry::make('primary_email')
                                ->label('E-mail')
                                ->state(fn (Person $record): ?string => $record->contactMethods()
                                    ->where('type', 'email')
                                    ->orderByDesc('is_primary')
                                    ->value('value'))
                                ->placeholder('—')
                                ->copyable()
                                ->columnSpan(6),
                            TextEntry::make('primary_phone')
                                ->label('Téléphone')
                                ->state(fn (Person $record): ?string => $record->contactMethods()
                                    ->where('type', 'phone')
                                    ->orderByDesc('is_primary')
                                    ->value('value'))
                                ->placeholder('—')
                                ->copyable()
                                ->columnSpan(6),
                            TextEntry::make('address_line_1')
                                ->label('Adresse')
                                ->placeholder('—')
                                ->columnSpan(8),
                            TextEntry::make('address_line_2')
                                ->label('Complément d’adresse')
                                ->placeholder('—')
                                ->columnSpan(4),
                            TextEntry::make('postal_code')
                                ->label('Code postal')
                                ->placeholder('—')
                                ->columnSpan(3),
                            TextEntry::make('city')
                                ->label('Ville')
                                ->placeholder('—')
                                ->columnSpan(5),
                            TextEntry::make('country_code')
                                ->label('Pays')
                                ->formatStateUsing(fn (?string $state): string => ClientProfileOptions::countryLabel($state))
                                ->placeholder('—')
                                ->columnSpan(4),
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
                Section::make('Suivi')
                    ->columnSpan(4)
                    ->columnStart(9)
                    ->schema([
                        TextEntry::make('assignedUser.name')
                            ->label('Responsable du suivi')
                            ->placeholder('Non attribué'),
                        TextEntry::make('status')
                            ->label('État de la fiche')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Active' : 'Archivée')
                            ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                        TextEntry::make('locale')
                            ->label('Langue privilégiée')
                            ->formatStateUsing(fn (?string $state): string => ClientProfileOptions::languageLabel($state))
                            ->placeholder('—'),
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
