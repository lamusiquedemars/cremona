<?php

namespace App\Filament\Resources\InstrumentAssets;

use App\Enums\InstrumentAssetStatus;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\InstrumentAssets\Pages\CreateInstrumentAsset;
use App\Filament\Resources\InstrumentAssets\Pages\EditInstrumentAsset;
use App\Filament\Resources\InstrumentAssets\Pages\ListInstrumentAssets;
use App\Models\InstrumentAsset;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstrumentAssetResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = InstrumentAsset::class;

    protected static ?string $navigationLabel = 'Instruments';

    protected static ?string $modelLabel = 'instrument';

    protected static ?string $pluralModelLabel = 'instruments';

    protected static ?string $presentationGroupKey = 'relation_client';

    protected static string $organizationModule = 'instruments';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMusicalNote;

    protected static string|\UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Fiche instrument')->schema([
                TextInput::make('name')->label('Intitulé')->required(),
                TextInput::make('reference')->label('Référence interne'),
                Select::make('family')->label('Famille')->options(['violon' => 'Violon', 'alto' => 'Alto', 'violoncelle' => 'Violoncelle', 'contrebasse' => 'Contrebasse', 'archet' => 'Archet', 'autre' => 'Autre']),
                TextInput::make('maker')->label('Luthier / fabricant'),
                TextInput::make('year')->label('Année'),
                Select::make('ownership')->label('Provenance')->options(['owned' => 'Propriété de l’atelier', 'deposit' => 'Dépôt-vente', 'consignment' => 'Confié par un tiers'])->default('owned')->required(),
                Select::make('status')->label('Disponibilité actuelle')->options(InstrumentAssetStatus::class)->default(InstrumentAssetStatus::Available)->required(),
                Textarea::make('description')->label('Description interne')->rows(4)->columnSpanFull(),
                KeyValue::make('attributes')->label('Caractéristiques de l’instrument')->keyLabel('Caractéristique')->valueLabel('Valeur')->columnSpanFull()->helperText('Ex. Longueur de corde : 328 mm. Ces données sont structurées et pourront être sélectionnées pour le site.'),
                Repeater::make('media')->label('Médias liés')->schema([
                    TextInput::make('url')->label('Adresse du média')->url()->required(),
                    TextInput::make('caption')->label('Légende'),
                    Checkbox::make('is_public')->label('Autoriser sur le site'),
                ])->columns(3)->columnSpanFull()->helperText('Liste les images ou documents déjà déposés dans la médiathèque. Seuls les médias autorisés seront transmis au site.'),
            ])->columns(2),
            Section::make('Mise à disposition')->schema([
                Checkbox::make('available_for_sale')->label('Proposer à la vente'),
                TextInput::make('suggested_sale_amount')->label('Prix de vente HT indicatif')->numeric()->prefix('€')->default(0),
                Checkbox::make('available_for_rental')->label('Proposer à la location'),
                TextInput::make('suggested_rental_amount')->label('Loyer HT indicatif')->numeric()->prefix('€')->default(0)->helperText('Montant de référence ; le montant est ajustable pour chaque location.'),
            ])->columns(2),
            Section::make('Visibilité sur le site')->description('Ces informations sont celles que le site public peut afficher. Elles ne modifient ni la location, ni la vente, ni le suivi atelier.')->schema([
                Checkbox::make('is_site_published')->label('Afficher cet instrument sur le site'),
                TextInput::make('public_title')->label('Titre affiché')->placeholder(fn (?InstrumentAsset $record): ?string => $record?->name),
                TextInput::make('public_slug')->label('Adresse publique')->helperText('Laissez vide pour utiliser la référence ou le titre.'),
                Textarea::make('public_description')->label('Présentation publique')->rows(4)->columnSpanFull(),
                TextInput::make('public_price_label')->label('Prix affiché')->placeholder('Ex. Sur demande ou 2 400 €'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('name')->label('Instrument')->searchable()->description(fn (InstrumentAsset $record): ?string => $record->maker),
            TextColumn::make('family')->label('Famille')->formatStateUsing(fn (?string $state): string => match ($state) {
                'violon' => 'Violon', 'alto' => 'Alto', 'violoncelle' => 'Violoncelle', 'contrebasse' => 'Contrebasse', 'archet' => 'Archet', default => 'Autre'
            })->placeholder('—'),
            TextColumn::make('status')->label('État')->badge(),
            IconColumn::make('available_for_sale')->label('Vente')->boolean(),
            IconColumn::make('available_for_rental')->label('Location')->boolean(),
        ])->filters([SelectFilter::make('status')->label('État')->options(InstrumentAssetStatus::class)])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouvel instrument')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInstrumentAssets::route('/'), 'create' => CreateInstrumentAsset::route('/create'), 'edit' => EditInstrumentAsset::route('/{record}/edit')];
    }
}
