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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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

    protected static ?string $presentationGroupKey = 'workshop';

    protected static ?string $presentationKey = 'instruments';

    protected static string $organizationModule = 'luthier_catalog';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMusicalNote;

    protected static string|\UnitEnum|null $navigationGroup = 'Atelier';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make('Fiche instrument')->schema([
                TextInput::make('name')->label('Intitulé')->required()->columnSpan(9),
                TextInput::make('reference')->label('Référence interne')->columnSpan(3),
                Select::make('family')->label('Famille')->options(['violon' => 'Violon', 'alto' => 'Alto', 'violoncelle' => 'Violoncelle', 'contrebasse' => 'Contrebasse', 'archet' => 'Archet', 'autre' => 'Autre'])->columnSpan(4),
                TextInput::make('maker')->label('Luthier / fabricant')->columnSpan(4),
                TextInput::make('year')->label('Année')->columnSpan(4),
                Select::make('ownership')->label('Provenance')->options(['owned' => 'Propriété de l’atelier', 'deposit' => 'Dépôt-vente', 'consignment' => 'Confié par un tiers'])->default('owned')->required()->columnSpan(6),
                Select::make('status')->label('Disponibilité actuelle')->options(InstrumentAssetStatus::class)->default(InstrumentAssetStatus::Available)->required()->columnSpan(6),
                Textarea::make('description')->label('Description interne')->rows(4)->columnSpanFull(),
                Repeater::make('attributes')->label('Caractéristiques de l’instrument')->schema([
                    Select::make('label')->label('Caractéristique')->options([
                        'Instrument' => 'Instrument',
                        'Taille' => 'Taille',
                        'Année' => 'Année',
                        'Longueur du corps' => 'Longueur du corps',
                        'Longueur de corde' => 'Longueur de corde',
                        'Bois de table' => 'Bois de table',
                        'Bois de fond et éclisses' => 'Bois de fond et éclisses',
                        'Vernis' => 'Vernis',
                        'État' => 'État',
                        'Montage' => 'Montage',
                        'Certificat ou expertise' => 'Certificat ou expertise',
                        '__other__' => 'Autre caractéristique',
                    ])->searchable()->required()->columnSpan(6),
                    TextInput::make('custom_label')->label('Autre caractéristique')->visible(fn (Get $get): bool => $get('label') === '__other__')->required(fn (Get $get): bool => $get('label') === '__other__'),
                    TextInput::make('value')->label('Valeur')->required()->columnSpan(4),
                    Checkbox::make('is_public')->label('Visible sur le site')->default(true)->columnSpan(2),
                ])->columns(12)->columnSpanFull()->helperText('Choisissez une caractéristique connue, ou « Autre caractéristique ». Seules les lignes cochées sont visibles sur le site.'),
                Repeater::make('media')->label('Photos')->schema([
                    FileUpload::make('path')->label('Photo')->image()->imageEditor()->disk('public')->directory('instruments')->visibility('public')->maxSize(10240)->required()->columnSpan(6),
                    Grid::make(1)->schema([
                        Checkbox::make('is_public')->label('Visible sur le site'),
                        TextInput::make('caption')->label('Légende'),
                    ])->columnSpan(6),
                ])->columns(12)->columnSpanFull()->helperText('Téléversez une photo ; les photos autorisées sont transmises au site.'),
            ])->columns(12)->columnSpanFull(),
            Section::make('Mise à disposition')->schema([
                Group::make([
                    Checkbox::make('available_for_sale')->label('Proposer à la vente')->columnSpan(6),
                    TextInput::make('suggested_sale_amount')->label('Prix de vente HT indicatif')->numeric()->prefix('€')->default(0)->columnSpan(6),
                ])->columns(12)->columnSpan(6),
                Group::make([
                    Checkbox::make('available_for_rental')->label('Proposer à la location')->columnSpan(6),
                    TextInput::make('suggested_rental_amount')->label('Loyer HT indicatif')->numeric()->prefix('€')->default(0)->columnSpan(6),
                ])->columns(12)->columnSpan(6)->extraAttributes(['class' => 'border-s border-gray-200 ps-6 dark:border-white/10']),
            ])->columns(12)->columnSpanFull(),
            Section::make('Visibilité sur le site')->description('Ces informations sont celles que le site public peut afficher. Elles ne modifient ni la location, ni la vente, ni le suivi atelier.')->schema([
                Group::make([
                    Checkbox::make('is_site_published')->label('Afficher cet instrument sur le site'),
                    TextInput::make('public_title')->label('Titre affiché')->placeholder(fn (?InstrumentAsset $record): ?string => $record?->name),
                    TextInput::make('public_slug')->label('Adresse publique')->helperText('Laissez vide pour utiliser la référence ou le titre.'),
                    TextInput::make('public_price_label')->label('Prix affiché')->placeholder('Ex. Sur demande ou 2 400 €'),
                ])->columnSpan(6),
                Textarea::make('public_description')->label('Présentation publique')->rows(7)->columnSpan(6),
            ])->columns(12)->columnSpanFull(),
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
