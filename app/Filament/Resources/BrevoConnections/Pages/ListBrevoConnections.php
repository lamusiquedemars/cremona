<?php

namespace App\Filament\Resources\BrevoConnections\Pages;

use App\Filament\Resources\BrevoConnections\BrevoConnectionResource;
use App\Models\OrganizationIntegration;
use App\Services\BrevoClient;
use App\Services\OrganizationIntegrationManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;

class ListBrevoConnections extends ListRecords
{
    protected static string $resource = BrevoConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configure')
                ->label(fn (): string => $this->integration() ? 'Configurer Brevo' : 'Connecter Brevo')
                ->icon(Heroicon::OutlinedLink)
                ->authorize(fn () => Gate::inspect('create', OrganizationIntegration::class))
                ->fillForm(function (): array {
                    $credentials = $this->integration()?->credentials ?? [];

                    return [
                        'api_key' => null,
                        'booking_url' => $credentials['booking_url'] ?? null,
                        'timezone' => $credentials['timezone'] ?? 'Europe/Paris',
                        'mode' => $credentials['mode'] ?? 'after_review',
                    ];
                })
                ->schema([
                    TextInput::make('api_key')
                        ->label('Clé API Brevo')
                        ->password()
                        ->revealable()
                        ->required(fn (): bool => blank($this->integration()?->credentials['api_key'] ?? null))
                        ->helperText('Laissez vide pour conserver la clé déjà enregistrée.'),
                    TextInput::make('booking_url')
                        ->label('Page de réservation Brevo')
                        ->url()
                        ->required()
                        ->maxLength(2048),
                    Select::make('mode')
                        ->label('Parcours public')
                        ->options([
                            'after_review' => 'Proposer la réservation après validation',
                            'direct' => 'Permettre la réservation directe',
                        ])
                        ->required(),
                    TextInput::make('timezone')
                        ->label('Fuseau professionnel')
                        ->required()
                        ->maxLength(64),
                ])
                ->modalHeading('Connexion à Brevo Meetings')
                ->modalSubmitActionLabel('Tester et enregistrer')
                ->action(function (array $data): void {
                    Gate::authorize('create', OrganizationIntegration::class);
                    $existingCredentials = $this->integration()?->credentials ?? [];
                    $apiKey = trim((string) ($data['api_key'] ?: ($existingCredentials['api_key'] ?? '')));

                    app(BrevoClient::class)->account($apiKey);

                    app(OrganizationIntegrationManager::class)->configure(
                        provider: 'brevo',
                        name: 'meetings',
                        credentials: [
                            'api_key' => $apiKey,
                            'booking_url' => $data['booking_url'],
                            'timezone' => $data['timezone'],
                            'mode' => $data['mode'],
                        ],
                        actor: auth()->user(),
                    );

                    Notification::make()
                        ->title('Brevo Meetings est connecté')
                        ->body('La clé a été vérifiée puis enregistrée de manière chiffrée.')
                        ->success()
                        ->send();
                }),
            Action::make('webhook')
                ->label(fn (): string => $this->integration()?->key_id
                    ? 'Régénérer le webhook'
                    : 'Configurer le webhook')
                ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                ->requiresConfirmation()
                ->modalDescription('Le précédent jeton cessera immédiatement de fonctionner. La nouvelle configuration ne sera affichée qu’une fois.')
                ->visible(fn (): bool => $this->integration()?->status === 'active')
                ->action(function (): void {
                    $integration = $this->integration();
                    Gate::authorize('update', $integration);
                    $token = app(OrganizationIntegrationManager::class)
                        ->rotateApiToken($integration, auth()->user());
                    $configuration = [
                        'authorization' => "Bearer {$token}",
                        'urls' => [
                            'booked' => route('api.v1.integrations.brevo.meetings.store', ['event' => 'booked']),
                            'started' => route('api.v1.integrations.brevo.meetings.store', ['event' => 'started']),
                            'cancelled' => route('api.v1.integrations.brevo.meetings.store', ['event' => 'cancelled']),
                        ],
                    ];
                    $json = json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    $this->js('navigator.clipboard.writeText('.Js::from($json).')');

                    Notification::make()
                        ->title('Configuration webhook copiée')
                        ->body("Conservez-la maintenant, elle ne sera plus affichée :\n{$json}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    private function integration(): ?OrganizationIntegration
    {
        return BrevoConnectionResource::getEloquentQuery()->first();
    }
}
