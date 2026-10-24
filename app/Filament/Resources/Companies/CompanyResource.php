<?php

namespace App\Filament\Resources\Companies;

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
use Filament\Schemas\Components\Utilities\Get;
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

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

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
            __('common.legal_name') => $record->legal_name,
            __('common.contact_details') => $record->contactMethods->pluck('value')->take(2)->implode(' · '),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make(fn (): string => app(OrganizationPresentation::class)->label('companies', 'Entreprise'))
                    ->description('Les informations d’identification de la structure.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)->schema([
                            TextInput::make('name')
                                ->label('Nom usuel')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(6),
                            TextInput::make('legal_name')
                                ->label(__('common.legal_name'))
                                ->maxLength(255)
                                ->columnSpan(6),
                            TextInput::make('website')
                                ->label(__('common.website'))
                                ->url()
                                ->maxLength(2048)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Coordonnées')
                    ->description('Les coordonnées générales de l’entreprise et son adresse postale.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(12)->schema([
                            TextInput::make('primary_email')
                                ->label('E-mail général')
                                ->placeholder('contact@entreprise.fr')
                                ->email()
                                ->maxLength(255)
                                ->columnSpan(6),
                            TextInput::make('primary_phone')
                                ->label('Téléphone')
                                ->placeholder('04 72 00 00 00')
                                ->tel()
                                ->maxLength(64)
                                ->columnSpan(6),
                            TextInput::make('address_line_1')
                                ->label('Adresse')
                                ->placeholder('12 rue des Luthiers')
                                ->maxLength(255)
                                ->required(fn (Get $get): bool => filled($get('address_line_2')) || filled($get('postal_code')) || filled($get('city')))
                                ->columnSpan(8),
                            TextInput::make('address_line_2')
                                ->label('Complément d’adresse')
                                ->placeholder('Bâtiment, étage, lieu-dit…')
                                ->maxLength(255)
                                ->columnSpan(4),
                            TextInput::make('postal_code')
                                ->label('Code postal')
                                ->placeholder('69001')
                                ->maxLength(32)
                                ->required(fn (Get $get): bool => $get('country_code') === 'FR' && (filled($get('address_line_1')) || filled($get('address_line_2')) || filled($get('city'))))
                                ->columnSpan(3),
                            TextInput::make('city')
                                ->label('Ville')
                                ->placeholder('Lyon')
                                ->maxLength(255)
                                ->required(fn (Get $get): bool => filled($get('address_line_1')) || filled($get('address_line_2')) || filled($get('postal_code')))
                                ->columnSpan(5),
                            Select::make('country_code')
                                ->label('Pays')
                                ->options(ClientProfileOptions::countries())
                                ->default('FR')
                                ->searchable()
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
                Section::make('Informations entreprise')
                    ->columnSpan(8)
                    ->schema([
                        Grid::make(12)->schema([
                            TextEntry::make('legal_name')
                                ->label(__('common.legal_name'))
                                ->placeholder('—')
                                ->columnSpan(6),
                            TextEntry::make('website')
                                ->label(__('common.website'))
                                ->url(fn (?string $state): ?string => $state)
                                ->openUrlInNewTab()
                                ->placeholder('—')
                                ->columnSpan(6),
                            TextEntry::make('primary_email')
                                ->label('E-mail général')
                                ->state(fn (Company $record): ?string => $record->contactMethods()
                                    ->where('type', 'email')
                                    ->orderByDesc('is_primary')
                                    ->value('value'))
                                ->placeholder('—')
                                ->copyable()
                                ->columnSpan(6),
                            TextEntry::make('primary_phone')
                                ->label('Téléphone')
                                ->state(fn (Company $record): ?string => $record->contactMethods()
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
                        TextEntry::make('people_count')
                            ->label(__('common.linked_contacts'))
                            ->state(fn (Company $record): int => $record->people()->count())
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('incoming_requests_count')
                            ->label(__('common.linked_requests'))
                            ->state(fn (Company $record): int => $record->incomingRequests()->count())
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('people.display_name')
                    ->label(__('common.contacts'))
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
                TextColumn::make('contactMethods.value')
                    ->label(__('common.contact_details'))
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList(),
                TextColumn::make('status')
                    ->label(__('common.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? __('common.active') : __('common.archived'))
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray')
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
                EditAction::make()->label('Modifier'),
            ])
            ->recordUrl(fn (Company $record): string => self::getUrl('view', ['record' => $record]));
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
