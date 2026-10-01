<?php

namespace App\Filament\Resources\InstrumentCategories;

use App\Filament\Concerns\UsesOrganizationModule;
use App\Filament\Resources\InstrumentCategories\Pages\ManageInstrumentCategories;
use App\Models\InstrumentCategory;
use App\Support\InstrumentRentalCatalog;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class InstrumentCategoryResource extends Resource
{
    use UsesOrganizationModule;

    protected static ?string $model = InstrumentCategory::class;
    protected static string $organizationModule = 'luthier_catalog';
    protected static ?string $navigationLabel = 'Grilles de location';
    protected static ?string $modelLabel = 'grille de location';
    protected static ?string $pluralModelLabel = 'grilles de location';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    protected static string|\UnitEnum|null $navigationGroup = 'Atelier';
    protected static ?int $navigationSort = 59;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            TextInput::make('name')->label('Libellé de la grille')->required()->maxLength(120)->columnSpan(5)->helperText('Ex. Violon enfant — étude.'),
            Select::make('family')->label('Famille d’instruments')->options(InstrumentRentalCatalog::families())->required()->live()->afterStateUpdated(fn (Set $set) => $set('eligible_sizes', []))->columnSpan(3),
            Select::make('rental_tier_id')->label('Gamme')->relationship('rentalTier', 'name', fn ($query) => $query->where('is_active', true))->required()->searchable()->preload()->columnSpan(4),
            CheckboxList::make('eligible_sizes')->label('Tailles couvertes')->options(fn (Get $get): array => InstrumentRentalCatalog::sizesFor($get('family')))->required()->columns(4)->columnSpan(7)->helperText('Un instrument de cette famille, de l’une de ces tailles et de cette gamme recevra cette grille automatiquement.'),
            TextInput::make('rental_monthly_amount')->label('Loyer mensuel HT')->numeric()->prefix('€')->required()->columnSpan(3)->helperText('Montant proposé lors de la création d’une location. Les locations existantes ne changent jamais.'),
            Toggle::make('is_active')->label('Grille active')->default(true)->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Grille')->searchable(),
            TextColumn::make('family')->label('Famille')->formatStateUsing(fn (?string $state): string => InstrumentRentalCatalog::families()[$state] ?? '—'),
            TextColumn::make('rentalTier.name')->label('Gamme')->placeholder('—'),
            TextColumn::make('eligible_sizes')->label('Tailles')->formatStateUsing(fn (?array $state, InstrumentCategory $record): string => collect($state)->map(fn (string $size): ?string => InstrumentRentalCatalog::sizeLabel($record->family, $size))->filter()->join(', ')),
            TextColumn::make('rental_monthly_amount')->label('Loyer mensuel HT')->money('EUR')->placeholder('À définir'),
            TextColumn::make('instruments_count')->label('Instruments tarifés')->counts('instruments'),
            ToggleColumn::make('is_active')->label('Active'),
        ])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouvelle catégorie')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInstrumentCategories::route('/')];
    }
}
