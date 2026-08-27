<?php
namespace App\Filament\Resources\Organizations\RelationManagers;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
class SitesRelationManager extends RelationManager
{
    protected static string $relationship = 'sites';
    protected static ?string $title = 'Sites rattachés';
    public function table(Table $table): Table { return $table->columns([TextColumn::make('name')->label('Site'), TextColumn::make('base_url')->label('Adresse')->url(fn (?string $state): ?string => $state), TextColumn::make('status')->label('Statut')->badge()])->headerActions([CreateAction::make()->label('Rattacher un site')->schema([TextInput::make('name')->label('Nom')->required(), TextInput::make('base_url')->label('Adresse')->url(), Select::make('status')->options(['active'=>'Actif','inactive'=>'Inactif'])->default('active')->required()])])->recordActions([DeleteAction::make()->label('Retirer')]); }
}
