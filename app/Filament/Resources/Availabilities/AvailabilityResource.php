<?php

namespace App\Filament\Resources\Availabilities;

use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\RelationManagers\AvailabilityMatchesRelationManager;
use App\Filament\Resources\Availabilities\Pages\CreateAvailability;
use App\Filament\Resources\Availabilities\Pages\EditAvailability;
use App\Filament\Resources\Availabilities\Pages\ListAvailabilities;
use App\Filament\Resources\Availabilities\Pages\ViewAvailability;
use App\Models\Availability;
use App\Models\InstrumentAsset;
use App\Support\ClientSearchCatalog;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AvailabilityResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = Availability::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;
    protected static string|UnitEnum|null $navigationGroup = 'Suivi client';
    protected static ?string $navigationLabel = 'Disponibilités';
    protected static ?string $modelLabel = 'disponibilité';
    protected static ?string $pluralModelLabel = 'disponibilités';
    protected static string $organizationModule = 'crm';
    protected static ?string $presentationGroupKey = 'customer_follow_up';
    protected static ?int $navigationSort = 45;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make('Objet disponible')->columnSpan(8)->schema([
                Select::make('instrument_asset_id')->label('Instrument enregistré')
                    ->relationship('instrumentAsset', 'name')
                    ->searchable()->preload()->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $instrument = $state ? InstrumentAsset::query()->find($state) : null;
                        if ($instrument === null) { return; }
                        $set('title', $instrument->name);
                        $set('object_type', $instrument->family);
                        $set('size', $instrument->rental_size);
                        $set('amount', $instrument->available_for_sale ? $instrument->suggested_sale_amount : null);
                    })
                    ->helperText('Facultatif : laissez vide pour une offre ponctuelle ou chez un confrère.'),
                TextInput::make('title')->label('Intitulé')->required()->maxLength(255),
                Grid::make(2)->schema([
                    Select::make('object_type')->label('Type d’objet')->options(ClientSearchCatalog::objectTypes())->required()->live(),
                    Select::make('size')->label('Taille')->options(fn (Get $get): array => ClientSearchCatalog::sizesFor($get('object_type')))->visible(fn (Get $get): bool => ClientSearchCatalog::usesInstrumentSizes($get('object_type'))),
                    TextInput::make('amount')->label('Prix')->numeric()->prefix('€')->minValue(0),
                    Select::make('source')->label('Origine')->options(['workshop' => 'À l’atelier', 'partner' => 'Chez un confrère', 'other' => 'Autre'])->default('workshop')->required()->live(),
                    TextInput::make('source_detail')->label('Précision sur l’origine')->maxLength(255)->visible(fn (Get $get): bool => $get('source') !== 'workshop'),
                ]),
                Textarea::make('notes')->label('Précisions libres')->rows(4)->maxLength(5000),
            ]),
            Section::make('État')->columnSpan(4)->schema([
                Select::make('status')->label('État')->options(['available' => 'Disponible', 'closed' => 'Terminée'])->default('available')->required(),
                TextEntry::make('match_count')->label('Clients à prévenir')->state(fn (?Availability $record): int => $record?->matches()->where('status', 'suggested')->count() ?? 0)->badge()->color('warning')->visible(fn (?Availability $record): bool => $record !== null),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Disponibilité')->schema([
                TextEntry::make('title')->label('Objet')->weight('semibold'),
                TextEntry::make('object_type')->label('Type')->formatStateUsing(fn (?string $state): ?string => ClientSearchCatalog::label($state)),
                TextEntry::make('size')->label('Taille')->placeholder('—'),
                TextEntry::make('amount')->label('Prix')->money('EUR')->placeholder('À confirmer'),
                TextEntry::make('source')->label('Origine')->formatStateUsing(fn (string $state): string => ['workshop' => 'À l’atelier', 'partner' => 'Chez un confrère', 'other' => 'Autre'][$state] ?? $state),
                TextEntry::make('source_detail')->label('Précision')->placeholder('—'),
                TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
            ])->columns(2),
            Section::make('Suivi')->schema([
                TextEntry::make('status')->label('État')->badge(),
                TextEntry::make('available_at')->label('Disponible depuis')->dateTime('d/m/Y H:i'),
                TextEntry::make('closed_at')->label('Clôturée le')->dateTime('d/m/Y H:i')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('available_at', 'desc')->columns([
            TextColumn::make('title')->label('Disponibilité')->searchable()->weight('medium')->description(fn (Availability $record): ?string => $record->source_detail)->sortable(),
            TextColumn::make('object_type')->label('Type')->formatStateUsing(fn (?string $state): ?string => ClientSearchCatalog::label($state))->badge()->sortable(),
            TextColumn::make('size')->label('Taille')->placeholder('—'),
            TextColumn::make('amount')->label('Prix')->money('EUR')->placeholder('À vérifier')->sortable(),
            TextColumn::make('matches_count')->counts('matches')->label('Correspondances')->badge()->color('warning'),
            TextColumn::make('status')->label('État')->badge()->formatStateUsing(fn (string $state): string => $state === 'available' ? 'Disponible' : 'Terminée'),
        ])->filters([
            SelectFilter::make('object_type')->label('Type')->options(ClientSearchCatalog::objectTypes()),
            SelectFilter::make('status')->label('État')->options(['available' => 'Disponible', 'closed' => 'Terminée']),
        ])->recordActions([ViewAction::make(), EditAction::make()->label('Modifier')])->recordUrl(fn (Availability $record): string => static::getUrl('view', ['record' => $record]))->headerActions([CreateAction::make()->label('Nouvelle disponibilité')]);
    }

    public static function getRelations(): array { return ['matches' => AvailabilityMatchesRelationManager::class]; }

    public static function getPages(): array
    {
        return ['index' => ListAvailabilities::route('/'), 'create' => CreateAvailability::route('/create'), 'view' => ViewAvailability::route('/{record}'), 'edit' => EditAvailability::route('/{record}/edit')];
    }
}
