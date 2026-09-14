<?php

namespace App\Filament\Resources\Companies;

use App\Enums\ContactMethodType;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\RelationManagers\AppointmentsRelationManager;
use App\Filament\RelationManagers\ConversationsRelationManager;
use App\Filament\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Filament\Resources\Companies\RelationManagers\IncomingRequestsRelationManager;
use App\Filament\Resources\Companies\RelationManagers\PeopleRelationManager;
use App\Models\Company;
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

class CompanyResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Suivi client';

    protected static ?string $navigationLabel = 'Entreprises';

    protected static ?string $presentationKey = 'companies';

    protected static string $organizationModule = 'crm';

    protected static ?string $presentationGroupKey = 'customer_follow_up';

    protected static ?string $modelLabel = 'entreprise';

    protected static ?string $pluralModelLabel = 'entreprises';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 30;

    protected static bool $isGloballySearchable = true;

    protected static int $globalSearchResultsLimit = 10;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'legal_name',
            'website',
            'contactMethods.value',
            'people.display_name',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['contactMethods', 'people']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Company $record */
        return array_filter([
            'Raison sociale' => $record->legal_name,
            'Coordonnées' => $record->contactMethods->pluck('value')->take(2)->implode(' · '),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(fn (): string => app(OrganizationPresentation::class)->label('companies', 'Entreprise'))
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nom courant')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('legal_name')
                                ->label('Raison sociale')
                                ->maxLength(255),
                            TextInput::make('website')
                                ->label(__('common.website'))
                                ->url()
                                ->maxLength(2048)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Repères')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('industry')
                            ->label(__('common.industry'))
                            ->maxLength(255),
                        TextInput::make('source')
                            ->label(__('common.source'))
                            ->maxLength(40),
                    ]),
                Section::make('Coordonnées')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('contactMethods')
                            ->label('Moyens de contact')
                            ->relationship()
                            ->schema([
                                Select::make('type')
                                    ->label(__('common.type'))
                                    ->options(ContactMethodType::class)
                                    ->required(),
                                TextInput::make('label')
                                    ->label(__('common.label'))
                                    ->placeholder('Accueil, facturation…')
                                    ->maxLength(255),
                                TextInput::make('value')
                                    ->label('Coordonnée')
                                    ->required()
                                    ->maxLength(255),
                                Toggle::make('is_primary')
                                    ->label(__('common.primary')),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Ajouter une coordonnée'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(fn (): string => app(OrganizationPresentation::class)->label('companies', 'Entreprise'))
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom courant')
                            ->weight('semibold')
                            ->size('lg'),
                        TextEntry::make('legal_name')
                            ->label('Raison sociale')
                            ->placeholder('—'),
                        TextEntry::make('website')
                            ->label(__('common.website'))
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab()
                            ->placeholder('—'),
                    ]),
                Section::make('Repères')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('common.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Active' : 'Archivée')
                            ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                    ]),
                Section::make('Coordonnées')
                    ->columnSpan(2)
                    ->schema([
                        RepeatableEntry::make('contactMethods')
                            ->label('')
                            ->schema([
                                TextEntry::make('type')->label(__('common.type'))->badge(),
                                TextEntry::make('value')->label('Coordonnée')->copyable()->weight('medium'),
                                TextEntry::make('label')->label(__('common.label'))->placeholder('—'),
                                IconEntry::make('is_primary')->label('Principale')->boolean(),
                            ])
                            ->columns(4),
                    ]),
                Section::make('Vue d’ensemble')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('people_count')
                            ->label('Contacts liés')
                            ->state(fn (Company $record): int => $record->people()->count())
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('incoming_requests_count')
                            ->label('Demandes liées')
                            ->state(fn (Company $record): int => $record->incomingRequests()->count())
                            ->badge()
                            ->color('info'),
                        TextEntry::make('last_activity_at')
                            ->label(__('common.last_activity'))
                            ->since()
                            ->placeholder(__('common.no_activity')),
                        TextEntry::make('created_at')
                            ->label('Créée le')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.company'))
                    ->description(fn (Company $record): ?string => $record->legal_name)
                    ->searchable(['name', 'legal_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('industry')
                    ->label(__('common.industry'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('people.display_name')
                    ->label(__('common.contacts'))
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
                TextColumn::make('contactMethods.value')
                    ->label('Coordonnées')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList(),
                TextColumn::make('status')
                    ->label(__('common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Active' : 'Archivée')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('common.status'))
                    ->options([
                        'active' => 'Active',
                        'archived' => 'Archivée',
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
            'contacts' => PeopleRelationManager::class,
            'requests' => IncomingRequestsRelationManager::class,
            'appointments' => AppointmentsRelationManager::class,
            'conversations' => ConversationsRelationManager::class,
            'notes' => NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'view' => ViewCompany::route('/{record}'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
