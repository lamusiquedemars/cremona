<?php

namespace App\Filament\Resources\Rentals;

use App\Enums\RentalStatus;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\Rentals\Pages\CreateRental;
use App\Filament\Resources\Rentals\Pages\EditRental;
use App\Filament\Resources\Rentals\Pages\ListRentals;
use App\Models\InstrumentAsset;
use App\Models\Rental;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RentalResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = Rental::class;

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $modelLabel = 'location';

    protected static ?string $pluralModelLabel = 'locations';

    protected static ?string $presentationGroupKey = 'relation_client';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?int $navigationSort = 61;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Location')->schema([
                TextInput::make('reference')->label('Référence')->helperText('Générée automatiquement si laissée vide.'),
                Select::make('status')->label('Statut')->options(RentalStatus::class)->default(RentalStatus::Draft)->disabled()->dehydrated()->required(),
                Select::make('instrument_asset_id')->label('Instrument')->relationship('instrument', 'name')->getOptionLabelFromRecordUsing(fn (InstrumentAsset $instrument): string => trim($instrument->name.' — '.$instrument->status->label()))->searchable()->required(),
                Select::make('person_id')->label('Client')->relationship('person', 'display_name')->searchable(),
                DatePicker::make('starts_on')->label('Début prévu')->native(false),
                DatePicker::make('expected_return_on')->label('Retour prévu')->native(false),
                DatePicker::make('returned_on')->label('Restitué le')->native(false),
                TextInput::make('unit_amount')->label('Loyer HT')->numeric()->prefix('€')->default(0),
                TextInput::make('deposit_amount')->label('Dépôt de garantie')->numeric()->prefix('€')->default(0),
                Textarea::make('notes')->label('Notes internes')->rows(4)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('reference')->label('Référence')->searchable(),
            TextColumn::make('instrument.name')->label('Instrument')->searchable(),
            TextColumn::make('person.display_name')->label('Client')->placeholder('—'),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('expected_return_on')->label('Retour prévu')->date('d/m/Y')->placeholder('—'),
            TextColumn::make('unit_amount')->label('Loyer HT')->money('EUR'),
        ])->filters([SelectFilter::make('status')->label('Statut')->options(RentalStatus::class)])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouvelle location')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRentals::route('/'), 'create' => CreateRental::route('/create'), 'edit' => EditRental::route('/{record}/edit')];
    }
}
