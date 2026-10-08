<?php

namespace App\Filament\Resources\RentalInsurancePlans;

use App\Filament\Concerns\UsesOrganizationModule;
use App\Filament\Resources\RentalInsurancePlans\Pages\ManageRentalInsurancePlans;
use App\Models\RentalInsurancePlan;
use App\Support\InstrumentRentalCatalog;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class RentalInsurancePlanResource extends Resource
{
    use UsesOrganizationModule;

    protected static ?string $model = RentalInsurancePlan::class;

    protected static string $organizationModule = 'luthier_catalog';

    protected static ?string $navigationLabel = 'Formules d’assurance';

    protected static ?string $modelLabel = 'formule d’assurance';

    protected static ?string $pluralModelLabel = 'formules d’assurance';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Atelier';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            TextInput::make('name')->label('Nom de la formule')->required()->maxLength(120)->columnSpan(5)->helperText('Ex. Assurance standard ou Assurance contrebasse.'),
            TextInput::make('monthly_amount')->label('Prime mensuelle')->numeric()->prefix('€')->required()->columnSpan(3),
            TextInput::make('clause_version')->label('Version des garanties')->maxLength(100)->columnSpan(2)->helperText('Ex. 2026-01.'),
            Toggle::make('is_active')->label('Formule active')->default(true)->columnSpan(2),
            Select::make('family')->label('Famille d’instruments')->options(InstrumentRentalCatalog::families())->placeholder('Toutes les familles')->live()->afterStateUpdated(fn (Set $set) => $set('eligible_sizes', []))->columnSpan(4),
            Select::make('rental_tier_id')->label('Gamme de location')->relationship('rentalTier', 'name', fn ($query) => $query->where('is_active', true))->searchable()->preload()->placeholder('Toutes les gammes')->columnSpan(4),
            CheckboxList::make('eligible_sizes')->label('Tailles couvertes')->options(fn (Get $get): array => InstrumentRentalCatalog::sizesFor($get('family')))->columns(4)->columnSpan(4)->helperText('Laissez vide pour couvrir toutes les tailles de la famille retenue.'),
            Textarea::make('coverage_summary')->label('Résumé des garanties')->rows(4)->columnSpanFull()->helperText('Texte repris dans le snapshot du contrat. Les clauses complètes restent dans le modèle contractuel validé.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Formule')->searchable()->wrap(),
            TextColumn::make('family')->label('Famille')->formatStateUsing(fn (?string $state): string => $state === null ? 'Toutes' : (InstrumentRentalCatalog::families()[$state] ?? $state)),
            TextColumn::make('rentalTier.name')->label('Gamme')->placeholder('Toutes'),
            TextColumn::make('monthly_amount')->label('Prime mensuelle')->money('EUR'),
            TextColumn::make('clause_version')->label('Garanties')->placeholder('—'),
            ToggleColumn::make('is_active')->label('Active'),
        ])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouvelle formule')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRentalInsurancePlans::route('/')];
    }
}
