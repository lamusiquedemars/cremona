<?php

namespace App\Filament\Resources\QuoteLineTemplates;

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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuoteLineTemplateResource extends Resource
{
    protected static ?string $model = QuoteLineTemplate::class;

    protected static ?string $navigationLabel = 'Lignes de devis enregistrées';

    protected static ?string $modelLabel = 'ligne de devis enregistrée';

    protected static ?string $pluralModelLabel = 'lignes de devis enregistrées';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?int $navigationSort = 65;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make('Ligne prête à ajouter à un devis')
                ->description('Elle préremplit le libellé, le texte et le tarif. Elle ne crée ni un produit, ni une prestation d’atelier : ces objets viendront dans le pack métier.')
                ->columns(12)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('label')
                        ->label('Nom affiché dans le devis')
                        ->placeholder('Ex. Reméchage d’archet')
                        ->helperText('Le titre court que tu retrouves quand tu ajoutes cette ligne.')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(6),
                    Select::make('kind')
                        ->label('De quoi s’agit-il ?')
                        ->options([
                            'service' => 'Prestation : travail réalisé',
                            'product' => 'Produit : article vendu',
                            'rental' => 'Location : mise à disposition',
                            'fee' => 'Frais : expédition, déplacement…',
                        ])
                        ->required()
                        ->columnSpan(3),
                    Checkbox::make('is_active')
                        ->label('Disponible lors de la création d’un devis')
                        ->helperText('Désactivez cette ligne pour la retirer de la sélection, sans modifier les devis existants.')
                        ->default(true)
                        ->columnSpan(3),
                    Textarea::make('description')
                        ->label('Texte détaillé repris dans le devis')
                        ->placeholder('Ex. Fourniture du chevalet, taille, ajustement et pose.')
                        ->helperText('Le texte est repris lors de l’ajout au devis. Une modification du devis n’altère pas cette ligne enregistrée.')
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
            TextColumn::make('label')->label('Libellé')->searchable(),
            TextColumn::make('kind')->label('Type')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                'service' => 'Prestation', 'product' => 'Produit', 'rental' => 'Location', 'fee' => 'Frais', default => $state,
            }),
            TextColumn::make('description')->label('Texte proposé')->limit(70)->tooltip(fn (QuoteLineTemplate $record): string => $record->description),
            TextColumn::make('default_unit_amount')->label('Prix HT')->money('EUR')->sortable(),
            IconColumn::make('is_active')->label('Actif')->boolean(),
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
