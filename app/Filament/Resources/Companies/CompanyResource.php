<?php

namespace App\Filament\Resources\Companies;

use App\Enums\ContactMethodType;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?string $navigationLabel = 'Entreprises';

    protected static ?string $modelLabel = 'entreprise';

    protected static ?string $pluralModelLabel = 'entreprises';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Entreprise')
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
                                ->label('Site internet')
                                ->url()
                                ->maxLength(2048)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Repères')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('industry')
                            ->label('Secteur')
                            ->maxLength(255),
                        TextInput::make('source')
                            ->label('Origine')
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
                                    ->label('Type')
                                    ->options(ContactMethodType::class)
                                    ->required(),
                                TextInput::make('label')
                                    ->label('Libellé')
                                    ->placeholder('Accueil, facturation…')
                                    ->maxLength(255),
                                TextInput::make('value')
                                    ->label('Coordonnée')
                                    ->required()
                                    ->maxLength(255),
                                Toggle::make('is_primary')
                                    ->label('Principal'),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Ajouter une coordonnée'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Entreprise')
                    ->description(fn (Company $record): ?string => $record->legal_name)
                    ->searchable(['name', 'legal_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('industry')
                    ->label('Secteur')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('people.display_name')
                    ->label('Contacts')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
                TextColumn::make('contactMethods.value')
                    ->label('Coordonnées')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Active' : 'Archivée')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active' => 'Active',
                        'archived' => 'Archivée',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
