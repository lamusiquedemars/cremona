<?php

namespace App\Filament\Resources\BrevoConnections;

use App\Filament\Resources\BrevoConnections\Pages\ListBrevoConnections;
use App\Models\OrganizationIntegration;
use App\Services\OrganizationIntegrationManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class BrevoConnectionResource extends Resource
{
    protected static ?string $model = OrganizationIntegration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Brevo Meetings';

    protected static ?string $modelLabel = 'connexion Brevo';

    protected static ?string $pluralModelLabel = 'connexion Brevo';

    protected static ?int $navigationSort = 90;

    protected static bool $isGloballySearchable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('provider', 'brevo')
            ->where('name', 'meetings');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Service')
                    ->formatStateUsing(fn (): string => 'Brevo Meetings')
                    ->weight('medium'),
                TextColumn::make('booking_url')
                    ->label('Page de réservation')
                    ->state(fn (OrganizationIntegration $record): ?string => $record->credentials['booking_url'] ?? null)
                    ->url(fn (?string $state): ?string => $state)
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                TextColumn::make('mode')
                    ->label('Parcours')
                    ->state(fn (OrganizationIntegration $record): string => match ($record->credentials['mode'] ?? 'after_review') {
                        'direct' => 'Réservation directe',
                        default => 'Après validation',
                    }),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Connecté' : 'Révoqué')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('updated_at')->label('Mis à jour')->since(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Révoquer')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OrganizationIntegration $record): bool => $record->status === 'active')
                    ->action(function (OrganizationIntegration $record): void {
                        Gate::authorize('update', $record);
                        app(OrganizationIntegrationManager::class)->revoke($record, auth()->user());
                        Notification::make()->title('Connexion Brevo révoquée')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBrevoConnections::route('/')];
    }
}
