<?php

namespace App\Filament\Resources\GoogleAdsConnections;

use App\Filament\Resources\GoogleAdsConnections\Pages\ListGoogleAdsConnections;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsReportingClient;
use App\Services\OrganizationIntegrationManager;
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
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class GoogleAdsConnectionResource extends Resource
{
    protected static ?string $model = OrganizationIntegration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Acquisition';

    protected static ?string $navigationLabel = 'Google Ads';

    protected static ?string $modelLabel = 'connexion Google Ads';

    protected static ?string $pluralModelLabel = 'connexion Google Ads';

    protected static ?int $navigationSort = 20;

    protected static bool $isGloballySearchable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('provider', 'google_ads')
            ->where('name', 'reporting');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('customer_id')
                ->label('Compte Google Ads')
                ->state(fn (OrganizationIntegration $record): ?string => self::formatCustomerId($record->credentials['customer_id'] ?? null))
                ->placeholder('—')
                ->copyable(),
            TextColumn::make('connection_status')
                ->label('État')
                ->state(fn (OrganizationIntegration $record): string => self::isReady($record->credentials) ? 'Prête à synchroniser' : 'Préparée — accès API à compléter')
                ->badge()
                ->color(fn (OrganizationIntegration $record): string => self::isReady($record->credentials) ? 'success' : 'warning'),
            TextColumn::make('updated_at')->label('Mis à jour')->since(),
        ])->recordActions([
            Action::make('sync')
                ->label('Synchroniser maintenant')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->visible(fn (OrganizationIntegration $record): bool => self::isReady($record->credentials))
                ->authorize('update')
                ->action(function (OrganizationIntegration $record): void {
                    $updated = app(GoogleAdsReportingClient::class)->sync($record);

                    Notification::make()
                        ->title('Google Ads synchronisé')
                        ->body("{$updated} journée(s) de campagne mise(s) à jour.")
                        ->success()
                        ->send();
                }),
            Action::make('authorize_google')
                ->label('Autoriser Google')
                ->icon(Heroicon::OutlinedLink)
                ->url(fn (OrganizationIntegration $record): string => route('google-ads.oauth.authorize', $record))
                ->openUrlInNewTab()
                ->visible(fn (OrganizationIntegration $record): bool => filled($record->credentials['oauth_client_id'] ?? null)
                    && filled($record->credentials['oauth_client_secret'] ?? null)
                    && blank($record->credentials['refresh_token'] ?? null)),
            Action::make('configure')
                ->label('Configurer')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->authorize(fn () => Gate::inspect('create', OrganizationIntegration::class))
                ->fillForm(function (OrganizationIntegration $record): array {
                    $credentials = $record->credentials;

                    return [
                        'customer_id' => self::formatCustomerId($credentials['customer_id'] ?? null),
                        'login_customer_id' => self::formatCustomerId($credentials['login_customer_id'] ?? null),
                        'developer_token' => null,
                        'oauth_client_id' => $credentials['oauth_client_id'] ?? null,
                        'oauth_client_secret' => null,
                        'refresh_token' => null,
                    ];
                })
                ->schema(self::configurationSchema())
                ->modalHeading('Connexion Google Ads')
                ->modalDescription('Le numéro de compte peut être enregistré maintenant. Les secrets OAuth et le developer token seront ajoutés seulement lors de l’activation de la synchronisation.')
                ->modalSubmitActionLabel('Enregistrer la préparation')
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
            TextInput::make('customer_id')
                ->label('Identifiant du compte client')
                ->helperText('Exemple : 200-507-3692. C’est le compte dont les dépenses seront lues.')
                ->required()
                ->regex('/^\d{3}-?\d{3}-?\d{4}$/'),
            TextInput::make('login_customer_id')
                ->label('Identifiant du compte administrateur (facultatif)')
                ->helperText('À renseigner seulement si un compte administrateur Google Ads est utilisé.')
                ->regex('/^\d{3}-?\d{3}-?\d{4}$/'),
            TextInput::make('developer_token')
                ->label('Developer token Google Ads')
                ->password()
                ->revealable()
                ->helperText('À ajouter plus tard depuis l’API Center Google Ads.'),
            TextInput::make('oauth_client_id')
                ->label('OAuth client ID')
                ->maxLength(255),
            TextInput::make('oauth_client_secret')
                ->label('OAuth client secret')
                ->password()
                ->revealable(),
            TextInput::make('refresh_token')
                ->label('OAuth refresh token')
                ->password()
                ->revealable()
                ->helperText('Ne pas saisir tant que l’autorisation Google n’a pas été effectuée.'),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function save(array $data, ?OrganizationIntegration $record = null): void
    {
        Gate::authorize('create', OrganizationIntegration::class);
        $existing = $record?->credentials ?? [];
        $credentials = [
            'customer_id' => self::normaliseCustomerId($data['customer_id']),
            'login_customer_id' => filled($data['login_customer_id'] ?? null)
                ? self::normaliseCustomerId($data['login_customer_id'])
                : null,
            'developer_token' => self::secret($data['developer_token'] ?? null, $existing['developer_token'] ?? null),
            'oauth_client_id' => $data['oauth_client_id'] ?: ($existing['oauth_client_id'] ?? null),
            'oauth_client_secret' => self::secret($data['oauth_client_secret'] ?? null, $existing['oauth_client_secret'] ?? null),
            'refresh_token' => self::secret($data['refresh_token'] ?? null, $existing['refresh_token'] ?? null),
        ];

        app(OrganizationIntegrationManager::class)->configure(
            provider: 'google_ads',
            name: 'reporting',
            credentials: $credentials,
            actor: auth()->user(),
        );

        Notification::make()
            ->title(self::isReady($credentials) ? 'Google Ads est prêt à être synchronisé' : 'Compte Google Ads préparé')
            ->body(self::isReady($credentials)
                ? 'Les identifiants ont été enregistrés de manière chiffrée.'
                : 'Les identifiants API pourront être ajoutés plus tard, sans perdre cette configuration.')
            ->success()
            ->send();
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

    private static function secret(?string $newValue, ?string $existingValue): ?string
    {
        return filled($newValue) ? trim($newValue) : $existingValue;
    }
}
