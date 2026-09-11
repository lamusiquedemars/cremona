<?php

namespace App\Filament\Resources\ContempoProjections;

use App\Filament\Concerns\UsesOrganizationConfiguration;
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
    use UsesOrganizationConfiguration;

    protected static ?string $model = OrganizationIntegration::class;

    protected static ?string $navigationLabel = 'Connecteurs de publication';

    protected static ?string $modelLabel = 'connecteur de publication';

    protected static ?string $pluralModelLabel = 'connecteurs de publication';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?int $navigationSort = 85;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('provider', 'contempo_cms')->where('name', 'instrument_projection');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('status')->label('État')->badge(), TextColumn::make('created_at')->label('Configurée le')->dateTime('d/m/Y H:i')])->headerActions([Action::make('configure')->label('Configurer le connecteur')->schema([TextInput::make('endpoint')->label('Adresse de réception du site')->url()->required(), TextInput::make('token')->label('Jeton du connecteur')->password()->revealable()->required()])->action(function (array $data): void {
            app(OrganizationIntegrationManager::class)->configure('contempo_cms', 'instrument_projection', $data, auth()->user());
            Notification::make()->title('Connecteur de publication configuré')->success()->send();
        })]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContempoProjections::route('/')];
    }
}
