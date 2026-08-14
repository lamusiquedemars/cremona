<?php

namespace App\Filament\Resources\People;

use App\Enums\ContactMethodType;
use App\Filament\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Models\Person;
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

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?string $navigationLabel = 'Contacts';

    protected static ?string $modelLabel = 'contact';

    protected static ?string $pluralModelLabel = 'contacts';

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Identité')
                    ->description('Les informations stables de la personne, indépendantes de ses demandes.')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('first_name')
                                ->label('Prénom')
                                ->maxLength(255),
                            TextInput::make('last_name')
                                ->label('Nom')
                                ->maxLength(255),
                            TextInput::make('display_name')
                                ->label('Nom affiché')
                                ->helperText('Calculé à partir du prénom et du nom si laissé vide.')
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Repères')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('locale')
                            ->label('Langue')
                            ->placeholder('fr')
                            ->maxLength(16),
                        TextInput::make('country_code')
                            ->label('Pays')
                            ->placeholder('FR')
                            ->length(2),
                        TextInput::make('source')
                            ->label('Origine')
                            ->maxLength(40),
                    ]),
                Section::make('Coordonnées')
                    ->description('Une personne peut avoir plusieurs adresses e-mail ou numéros de téléphone.')
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
                                    ->placeholder('Professionnel, mobile…')
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
            ->defaultSort('display_name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Contact')
                    ->searchable(['display_name', 'first_name', 'last_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('contactMethods.value')
                    ->label('Coordonnées')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable(),
                TextColumn::make('companies.name')
                    ->label('Entreprises')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Actif' : 'Archivé')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('updated_at')
                    ->label('Mis à jour')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active' => 'Actif',
                        'archived' => 'Archivé',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'create' => CreatePerson::route('/create'),
            'edit' => EditPerson::route('/{record}/edit'),
        ];
    }
}
