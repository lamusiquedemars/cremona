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
            ->columns(3)
            ->components([
                Section::make(__('cremona.person.identity'))
                    ->description(__('cremona.person.identity_description'))
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('first_name')
                                ->label(__('cremona.request.first_name'))
                                ->maxLength(255),
                            TextInput::make('last_name')
                                ->label(__('cremona.request.last_name'))
                                ->maxLength(255),
                            TextInput::make('display_name')
                                ->label(__('cremona.request.display_name'))
                                ->helperText(__('cremona.person.display_name_help'))
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make(__('cremona.person.reference_points'))
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('locale')
                            ->label(__('cremona.person.language'))
                            ->placeholder('fr')
                            ->maxLength(16),
                        TextInput::make('country_code')
                            ->label(__('cremona.person.country'))
                            ->placeholder('FR')
                            ->length(2),
                        TextInput::make('source')
                            ->label(__('cremona.request.origin'))
                            ->maxLength(40),
                    ]),
                Section::make(__('cremona.person.contact_details'))
                    ->description(__('cremona.person.contact_details_description'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('contactMethods')
                            ->label(__('cremona.person.contact_methods'))
                            ->relationship()
                            ->schema([
                                Select::make('type')
                                    ->label(__('cremona.person.type'))
                                    ->options(ContactMethodType::class)
                                    ->required(),
                                TextInput::make('label')
                                    ->label(__('cremona.person.label'))
                                    ->placeholder(__('cremona.person.label_placeholder'))
                                    ->maxLength(255),
                                TextInput::make('value')
                                    ->label(__('cremona.person.contact_detail'))
                                    ->required()
                                    ->maxLength(255),
                                Toggle::make('is_primary')
                                    ->label(__('cremona.person.primary')),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel(__('cremona.person.add_contact_detail')),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(fn (): string => app(OrganizationPresentation::class)->label('contacts', 'Contact'))
                    ->columnSpan(2)
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
                Section::make(__('common.reference_points'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('common.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'active' ? __('common.active') : __('common.archived'))
                            ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                        TextEntry::make('source')->label(__('common.source'))->placeholder('—'),
                        TextEntry::make('locale')->label(__('common.language'))->placeholder('—'),
                        TextEntry::make('country_code')->label(__('common.country'))->placeholder('—'),
                        TextEntry::make('assignedUser.name')->label(__('common.assignee'))->placeholder(__('common.unassigned')),
                    ]),
                Section::make(__('common.contact_details'))
                    ->columnSpan(2)
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
                    ]),
                Section::make(__('common.overview'))
                    ->columnSpan(1)
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
