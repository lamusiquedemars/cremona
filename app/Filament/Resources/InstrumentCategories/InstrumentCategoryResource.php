<?php

namespace App\Filament\Resources\InstrumentCategories;

use App\Filament\Concerns\UsesOrganizationModule;
use App\Filament\Resources\InstrumentCategories\Pages\ManageInstrumentCategories;
use App\Models\InstrumentCategory;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class InstrumentCategoryResource extends Resource
{
    use UsesOrganizationModule;

    protected static ?string $model = InstrumentCategory::class;
    protected static string $organizationModule = 'luthier_catalog';
    protected static ?string $navigationLabel = 'Catégories et tarifs';
    protected static ?string $modelLabel = 'catégorie d’instrument';
    protected static ?string $pluralModelLabel = 'catégories d’instruments';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    protected static string|\UnitEnum|null $navigationGroup = 'Atelier';
    protected static ?int $navigationSort = 59;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            TextInput::make('name')->label('Catégorie')->required()->maxLength(120)->columnSpan(5),
            TextInput::make('rental_monthly_amount')->label('Loyer mensuel HT')->numeric()->prefix('€')->columnSpan(4)->helperText('Montant proposé lors de la création d’une location. Les locations existantes ne changent jamais.'),
            Toggle::make('is_active')->label('Catégorie active')->default(true)->columnSpan(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Catégorie')->searchable(),
            TextColumn::make('rental_monthly_amount')->label('Loyer mensuel HT')->money('EUR')->placeholder('À définir'),
            TextColumn::make('instruments_count')->label('Instruments')->counts('instruments'),
            ToggleColumn::make('is_active')->label('Active'),
        ])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouvelle catégorie')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInstrumentCategories::route('/')];
    }
}
