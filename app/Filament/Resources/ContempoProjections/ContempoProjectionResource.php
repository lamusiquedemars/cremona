<?php

namespace App\Filament\Resources\ContempoProjections;

use App\Filament\Concerns\UsesOrganizationModule;
use App\Filament\Resources\ContempoProjections\Pages\ListContempoProjections;
use App\Models\OrganizationIntegration;
use App\Services\OrganizationIntegrationManager;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContempoProjectionResource extends Resource
{
    use UsesOrganizationModule;

    protected static ?string $model = OrganizationIntegration::class;

    protected static string $organizationModule = 'contempo';

    protected static ?string $navigationLabel = 'Projection Contempo';

    protected static ?string $modelLabel = 'projection Contempo';

    protected static ?string $pluralModelLabel = 'projection Contempo';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?int $navigationSort = 85;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('provider', 'contempo_cms')->where('name', 'instrument_projection');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('status')->label('État')->badge(), TextColumn::make('created_at')->label('Configurée le')->dateTime('d/m/Y H:i')])->headerActions([Action::make('configure')->label('Configurer la projection')->schema([TextInput::make('endpoint')->label('Adresse de réception Contempo')->url()->required(), TextInput::make('token')->label('Jeton de projection')->password()->revealable()->required()])->action(function (array $data): void {
            app(OrganizationIntegrationManager::class)->configure('contempo_cms', 'instrument_projection', $data, auth()->user());
            Notification::make()->title('Projection Contempo configurée')->success()->send();
        })]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContempoProjections::route('/')];
    }
}
