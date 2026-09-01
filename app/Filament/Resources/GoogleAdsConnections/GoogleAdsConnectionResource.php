<?php

namespace App\Filament\Resources\GoogleAdsConnections;

use App\Filament\Resources\GoogleAdsConnections\Pages\ListGoogleAdsConnections;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsCredentials;
use App\Services\GoogleAdsReportingClient;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Throwable;
use UnitEnum;

class GoogleAdsConnectionResource extends Resource
{
    protected static ?string $model = OrganizationIntegration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?string $navigationLabel = 'Publicité';

    protected static ?string $modelLabel = 'compte Google Ads';

    protected static ?string $pluralModelLabel = 'Compte Google Ads';

    protected static ?int $navigationSort = 100;

    protected static bool $isGloballySearchable = false;

    public static function getOrganizationTimezone(): string
    {
        return app(OrganizationContext::class)->require()->timezone();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('provider', 'google_ads')->where('name', 'reporting');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('customer_id')
                ->label('Compte Google Ads')
                ->state(fn (OrganizationIntegration $record): ?string => self::formatCustomerId($record->credentials['customer_id'] ?? null))
                ->description('Compte détenu par cette organisation')
                ->placeholder('—')
                ->copyable(),
            TextColumn::make('connection_status')
                ->label('État de connexion')
                ->state(fn (OrganizationIntegration $record): string => app(GoogleAdsCredentials::class)->connectionState($record->credentials))
                ->description('Infrastructure technique gérée par Maracuja')
                ->badge()
                ->color(fn (OrganizationIntegration $record): string => self::isReady($record->credentials) ? 'success' : 'warning'),
            TextColumn::make('last_synced_at')
                ->label('Dernière synchronisation')
                ->state(fn (OrganizationIntegration $record): ?string => $record->credentials['last_synced_at'] ?? null)
                ->dateTime('d/m/Y H:i')
                ->timezone(fn (): string => static::getOrganizationTimezone())
                ->placeholder('Jamais'),
        ])->recordActions([
            Action::make('sync')
                ->label('Synchroniser les résultats')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->visible(fn (OrganizationIntegration $record): bool => self::isReady($record->credentials))
                ->authorize('update')
                ->requiresConfirmation()
                ->modalDescription('Cette action lit les résultats Google Ads. Elle ne crée et ne modifie aucune campagne.')
                ->action(function (OrganizationIntegration $record): void {
                    try {
                        $updated = app(GoogleAdsReportingClient::class)->sync($record);
                        Notification::make()->title('Résultats Google Ads synchronisés')
                            ->body("{$updated} journée(s) de campagne mise(s) à jour.")->success()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('Synchronisation Google Ads refusée')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Action::make('sync_diagnostic')
                ->label('Voir le diagnostic')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning')
                ->visible(fn (OrganizationIntegration $record): bool => filled($record->credentials['last_sync_error'] ?? null))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->modalHeading('Diagnostic de la dernière synchronisation')
                ->modalDescription(fn (OrganizationIntegration $record): string => self::syncDiagnostic($record)),
            Action::make('clear_sync_diagnostic')
                ->label('Effacer le diagnostic')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->visible(fn (OrganizationIntegration $record): bool => filled($record->credentials['last_sync_error'] ?? null))
                ->authorize('update')
                ->requiresConfirmation()
                ->modalDescription('Cette action efface uniquement le message affiché dans Cremona. Elle ne modifie ni Google Ads, ni la campagne.')
                ->action(function (OrganizationIntegration $record): void {
                    $credentials = $record->credentials;
                    unset($credentials['last_sync_error'], $credentials['last_sync_failed_at']);
                    $record->update(['credentials' => $credentials]);
                    Notification::make()->title('Diagnostic effacé')->success()->send();
                }),
            Action::make('configure')
                ->label('Modifier le compte')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->authorize(fn () => Gate::inspect('create', OrganizationIntegration::class))
                ->fillForm(fn (OrganizationIntegration $record): array => [
                    'customer_id' => self::formatCustomerId($record->credentials['customer_id'] ?? null),
                ])
                ->schema(self::configurationSchema())
                ->modalHeading('Compte Google Ads de l’organisation')
                ->modalDescription('Ce compte appartient au client. L’infrastructure API Maracuja est gérée séparément et n’est jamais affichée ici.')
                ->modalSubmitActionLabel('Enregistrer le compte')
                ->action(fn (array $data, OrganizationIntegration $record) => self::save($data, $record)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListGoogleAdsConnections::route('/')];
    }

    /** @return array<int, Component> */
    public static function configurationSchema(): array
    {
        return [
            TextInput::make('customer_id')->label('Identifiant du compte client')
                ->helperText('Exemple : 200-507-3692. C’est le compte dont les résultats seront lus.')
                ->required()->regex('/^\d{3}-?\d{3}-?\d{4}$/'),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function save(array $data, ?OrganizationIntegration $record = null): void
    {
        Gate::authorize('create', OrganizationIntegration::class);
        $credentials = [...($record?->credentials ?? []), 'customer_id' => self::normaliseCustomerId($data['customer_id'])];

        app(OrganizationIntegrationManager::class)->configure(
            provider: 'google_ads', name: 'reporting', credentials: $credentials, actor: auth()->user(),
        );

        Notification::make()
            ->title(self::isReady($credentials) ? 'Compte Google Ads connecté' : 'Compte Google Ads enregistré')
            ->body(self::isReady($credentials)
                ? 'La connexion existante a été conservée.'
                : 'Autorisez Google Ads lorsque l’action sera disponible. L’infrastructure technique reste gérée par Maracuja.')
            ->success()->send();
    }

    /** @param array<string, mixed> $credentials */
    public static function isReady(array $credentials): bool
    {
        return app(GoogleAdsCredentials::class)->isReady($credentials);
    }

    private static function normaliseCustomerId(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? $value;
    }

    private static function formatCustomerId(?string $value): ?string
    {
        $value = filled($value) ? self::normaliseCustomerId($value) : null;

        return $value !== null && strlen($value) === 10
            ? substr($value, 0, 3).'-'.substr($value, 3, 3).'-'.substr($value, 6)
            : $value;
    }

    private static function syncDiagnostic(OrganizationIntegration $record): string
    {
        $credentials = $record->credentials;
        $when = filled($credentials['last_sync_failed_at'] ?? null)
            ? 'Le '.Carbon::parse($credentials['last_sync_failed_at'])->timezone(static::getOrganizationTimezone())->format('d/m/Y H:i').'.'
            : 'Date non disponible.';

        return $when.' '.(string) $credentials['last_sync_error'];
    }
}
