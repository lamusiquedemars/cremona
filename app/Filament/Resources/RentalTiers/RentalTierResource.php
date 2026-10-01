<?php

namespace App\Filament\Resources\RentalTiers;

use App\Filament\Concerns\UsesOrganizationModule;
use App\Filament\Resources\RentalTiers\Pages\ManageRentalTiers;
use App\Models\RentalTier;
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

class RentalTierResource extends Resource
{
    use UsesOrganizationModule;

    protected static ?string $model = RentalTier::class;
    protected static string $organizationModule = 'luthier_catalog';
    protected static ?string $navigationLabel = 'Gammes de location';
    protected static ?string $modelLabel = 'gamme de location';
    protected static ?string $pluralModelLabel = 'gammes de location';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;
    protected static string|\UnitEnum|null $navigationGroup = 'Atelier';
    protected static ?int $navigationSort = 58;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            TextInput::make('name')->label('Gamme')->required()->maxLength(80)->columnSpan(8)->helperText('Ex. Étude, Avancé ou Professionnel. La gamme qualifie l’instrument ; elle ne porte pas de prix.'),
            Toggle::make('is_active')->label('Gamme active')->default(true)->columnSpan(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Gamme')->searchable(),
            TextColumn::make('instruments_count')->label('Instruments')->counts('instruments'),
            TextColumn::make('categories_count')->label('Grilles tarifaires')->counts('categories'),
            ToggleColumn::make('is_active')->label('Active'),
        ])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouvelle gamme')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRentalTiers::route('/')];
    }
}
