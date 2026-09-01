<?php

namespace App\Filament\Resources\GoogleAdsConnections;

use App\Filament\Resources\GoogleAdsConnections\Pages\ListGoogleAdsConnections;
use App\Models\OrganizationIntegration;
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
                ->state(fn (OrganizationIntegration $record): string => self::isReady($record->credentials) ? 'Prêt à synchroniser' : 'Configuration à compléter')
                ->description('Credentials propres à cette organisation')
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
            Action::make('authorize_google')
                ->label('Autoriser Google')
                ->icon(Heroicon::OutlinedLink)
                ->url(fn (OrganizationIntegration $record): string => route('google-ads.oauth.authorize', $record))
                ->openUrlInNewTab()
                ->visible(fn (OrganizationIntegration $record): bool => filled($record->credentials['oauth_client_id'] ?? null)
                    && filled($record->credentials['oauth_client_secret'] ?? null)
                    && blank($record->credentials['refresh_token'] ?? null)),
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
                    'login_customer_id' => self::formatCustomerId($record->credentials['login_customer_id'] ?? null),
                    'developer_token' => null,
                    'oauth_client_id' => $record->credentials['oauth_client_id'] ?? null,
                    'oauth_client_secret' => null,
                    'refresh_token' => null,
                ])
                ->schema(self::configurationSchema())
                ->modalHeading('Compte Google Ads de l’organisation')
                ->modalDescription('Les credentials sont chiffrés et rattachés uniquement à cette organisation.')
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
            TextInput::make('login_customer_id')->label('Identifiant du compte administrateur (facultatif)')
                ->regex('/^\d{3}-?\d{3}-?\d{4}$/'),
            TextInput::make('developer_token')->label('Developer token Google Ads')->password()->revealable(),
            TextInput::make('oauth_client_id')->label('OAuth client ID')->maxLength(255),
            TextInput::make('oauth_client_secret')->label('OAuth client secret')->password()->revealable(),
            TextInput::make('refresh_token')->label('OAuth refresh token')->password()->revealable(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function save(array $data, ?OrganizationIntegration $record = null): void
    {
        Gate::authorize('create', OrganizationIntegration::class);
        $existing = $record?->credentials ?? [];
        $credentials = [
            'customer_id' => self::normaliseCustomerId($data['customer_id']),
            'login_customer_id' => filled($data['login_customer_id'] ?? null) ? self::normaliseCustomerId($data['login_customer_id']) : null,
            'developer_token' => self::secret($data['developer_token'] ?? null, $existing['developer_token'] ?? null),
            'oauth_client_id' => $data['oauth_client_id'] ?: ($existing['oauth_client_id'] ?? null),
            'oauth_client_secret' => self::secret($data['oauth_client_secret'] ?? null, $existing['oauth_client_secret'] ?? null),
            'refresh_token' => self::secret($data['refresh_token'] ?? null, $existing['refresh_token'] ?? null),
        ];

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
        return filled($credentials['customer_id'] ?? null)
            && filled($credentials['developer_token'] ?? null)
            && filled($credentials['oauth_client_id'] ?? null)
            && filled($credentials['oauth_client_secret'] ?? null)
            && filled($credentials['refresh_token'] ?? null);
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

    private static function secret(?string $newValue, ?string $existingValue): ?string
    {
        return filled($newValue) ? trim($newValue) : $existingValue;
    }
}
