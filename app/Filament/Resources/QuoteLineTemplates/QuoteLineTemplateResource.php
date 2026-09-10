<?php

namespace App\Filament\Resources\QuoteLineTemplates;

use App\Filament\Concerns\UsesOrganizationModule;
use App\Filament\Resources\QuoteLineTemplates\Pages\CreateQuoteLineTemplate;
use App\Filament\Resources\QuoteLineTemplates\Pages\EditQuoteLineTemplate;
use App\Filament\Resources\QuoteLineTemplates\Pages\ListQuoteLineTemplates;
use App\Models\QuoteLineTemplate;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class QuoteLineTemplateResource extends Resource
{
    use UsesOrganizationModule;

    protected static ?string $model = QuoteLineTemplate::class;

    protected static string $organizationModule = 'quotes';

    protected static ?string $navigationLabel = 'Lignes de devis enregistrées';

    protected static ?string $modelLabel = 'ligne de devis enregistrée';

    protected static ?string $pluralModelLabel = 'lignes de devis enregistrées';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?int $navigationSort = 65;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make('Ligne de devis enregistrée')
                ->description('Cette ligne préremplit l’intitulé, la description et le tarif lors de son ajout à un devis.')
                ->columns(12)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('label')
                        ->label('Intitulé')
                        ->placeholder('Ex. Reméchage d’archet')
                        ->helperText('Exemple : Reméchage d’archet.')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(6),
                    Select::make('kind')
                        ->label('Type de ligne')
                        ->options([
                            'service' => 'Prestation : travail réalisé',
                            'product' => 'Produit : article vendu',
                            'rental' => 'Location : mise à disposition',
                            'fee' => 'Frais : expédition, déplacement…',
                        ])
                        ->required()
                        ->columnSpan(3),
                    Checkbox::make('is_active')
                        ->label('Ligne active')
                        ->helperText('Désactivez cette ligne pour la retirer de la sélection, sans modifier les devis existants.')
                        ->default(true)
                        ->columnSpan(3),
                    Textarea::make('description')
                        ->label('Description par défaut, modifiable pour chaque devis')
                        ->placeholder('Ex. Fourniture du chevalet, taille, ajustement et pose.')
                        ->helperText('Elle est copiée dans le devis au moment de l’ajout.')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),
                    TextInput::make('default_quantity')
                        ->label('Quantité proposée')
                        ->numeric()
                        ->minValue(0.01)
                        ->default(1)
                        ->columnSpan(4),
                    TextInput::make('default_unit_amount')
                        ->label('Prix unitaire HT proposé')
                        ->helperText('0 € signifie : à décider au cas par cas.')
                        ->numeric()
                        ->prefix('€')
                        ->default(0)
                        ->columnSpan(4),
                    TextInput::make('default_tax_rate')
                        ->label('TVA proposée')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('%')
                        ->default(0)
                        ->columnSpan(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('label')->columns([
            TextColumn::make('label')->label('Intitulé')->searchable(),
            TextColumn::make('kind')->label('Type')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                'service' => 'Prestation', 'product' => 'Produit', 'rental' => 'Location', 'fee' => 'Frais', default => $state,
            }),
            TextColumn::make('description')->label('Description')->limit(70)->tooltip(fn (QuoteLineTemplate $record): string => $record->description),
            TextColumn::make('default_unit_amount')->label('Prix HT')->money('EUR')->sortable(),
            ToggleColumn::make('is_active')->label('Ligne active'),
        ])->recordActions([EditAction::make()])->headerActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuoteLineTemplates::route('/'),
            'create' => CreateQuoteLineTemplate::route('/create'),
            'edit' => EditQuoteLineTemplate::route('/{record}/edit'),
        ];
    }
}
