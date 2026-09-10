<?php

namespace App\Filament\Resources\ServiceDefinitions;

use App\Filament\Concerns\UsesOrganizationModule;
use App\Filament\Resources\ServiceDefinitions\Pages\CreateServiceDefinition;
use App\Filament\Resources\ServiceDefinitions\Pages\EditServiceDefinition;
use App\Filament\Resources\ServiceDefinitions\Pages\ListServiceDefinitions;
use App\Models\ServiceDefinition;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ServiceDefinitionResource extends Resource
{
    use UsesOrganizationModule;

    protected static ?string $model = ServiceDefinition::class;

    protected static string $organizationModule = 'interventions';

    protected static ?string $navigationLabel = 'Prestations atelier';

    protected static ?string $modelLabel = 'prestation atelier';

    protected static ?string $pluralModelLabel = 'prestations atelier';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([Section::make('Prestation')->columns(12)->columnSpanFull()->schema([
            TextInput::make('name')->label('Intitulé')->required()->columnSpan(6),
            Checkbox::make('is_active')->label('Prestation active')->default(true)->columnSpan(3),
            TextInput::make('suggested_unit_amount')->label('Prix HT indicatif')->numeric()->prefix('€')->default(0)->columnSpan(3),
            Textarea::make('description')->label('Description par défaut')->rows(4)->columnSpan(8),
            TextInput::make('tax_rate')->label('TVA')->numeric()->suffix('%')->default(0)->columnSpan(4),
        ])]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Prestation')->searchable(), TextColumn::make('description')->label('Description')->limit(70), TextColumn::make('suggested_unit_amount')->label('Prix HT')->money('EUR'), ToggleColumn::make('is_active')->label('Active')])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouvelle prestation')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListServiceDefinitions::route('/'), 'create' => CreateServiceDefinition::route('/create'), 'edit' => EditServiceDefinition::route('/{record}/edit')];
    }
}
