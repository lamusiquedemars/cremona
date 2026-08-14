<?php

namespace App\Filament\Resources\InboundChannels;

use App\Filament\Resources\InboundChannels\Pages\ListInboundChannels;
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

class InboundChannelResource extends Resource
{
    protected static ?string $model = OrganizationIntegration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Canaux entrants';

    protected static ?string $modelLabel = 'canal entrant';

    protected static ?string $pluralModelLabel = 'canaux entrants';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 80;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('provider', 'maracuja_cms')
            ->whereNotNull('key_id');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'active')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Canal')
                    ->description('Maracuja CMS')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Actif' : 'Révoqué')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('key_id')
                    ->label('Identifiant')
                    ->copyable()
                    ->fontFamily('mono'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('revoked_at')
                    ->label('Révoqué le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Révoquer')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Les prochains envois utilisant ce jeton seront immédiatement refusés.')
                    ->visible(fn (OrganizationIntegration $record): bool => $record->status === 'active')
                    ->action(function (OrganizationIntegration $record): void {
                        Gate::authorize('update', $record);
                        app(OrganizationIntegrationManager::class)->revoke($record, auth()->user());
                        Notification::make()
                            ->title('Canal révoqué')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInboundChannels::route('/'),
        ];
    }
}
