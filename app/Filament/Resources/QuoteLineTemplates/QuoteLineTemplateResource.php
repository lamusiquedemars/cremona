<?php

namespace App\Filament\Resources\QuoteLineTemplates;

use App\Filament\Resources\QuoteLineTemplates\Pages\CreateQuoteLineTemplate;
use App\Filament\Resources\QuoteLineTemplates\Pages\EditQuoteLineTemplate;
use App\Filament\Resources\QuoteLineTemplates\Pages\ListQuoteLineTemplates;
use App\Models\QuoteLineTemplate;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
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

    protected static ?string $navigationLabel = 'Modèles commerciaux';

    protected static ?string $modelLabel = 'modèle de ligne';

    protected static ?string $pluralModelLabel = 'modèles commerciaux';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?int $navigationSort = 65;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Modèle commercial')->columns(3)->schema([
                TextInput::make('code')->label('Code interne')->maxLength(80),
                TextInput::make('label')->label('Libellé')->required()->maxLength(255)->columnSpan(2),
                TextInput::make('kind')->label('Type')->maxLength(80)->placeholder('Prestation, pièce, location…'),
                Textarea::make('description')->label('Description reprise dans le devis')->required()->rows(3)->columnSpan(2),
                TextInput::make('default_quantity')->label('Quantité par défaut')->numeric()->minValue(0.01)->default(1),
                TextInput::make('default_unit_amount')->label('Prix unitaire HT')->numeric()->prefix('€')->default(0),
                TextInput::make('default_tax_rate')->label('TVA (%)')->numeric()->minValue(0)->default(0),
                Checkbox::make('is_active')->label('Disponible à l’ajout')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('label')->columns([
            TextColumn::make('code')->label('Code')->searchable()->toggleable(),
            TextColumn::make('label')->label('Libellé')->searchable(),
            TextColumn::make('kind')->label('Type')->badge()->placeholder('—'),
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
