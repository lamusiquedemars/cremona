<?php

namespace App\Filament\Resources\InboundChannels\Pages;

use App\Filament\Resources\InboundChannels\InboundChannelResource;
use App\Models\OrganizationIntegration;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;

class ListInboundChannels extends ListRecords
{
    protected static string $resource = InboundChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createChannel')
                ->label('Nouveau canal')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom du canal')
                        ->placeholder('site-principal')
                        ->helperText('Utilisez un nom qui identifie clairement le site ou le formulaire émetteur.')
                        ->required()
                        ->maxLength(255)
                        ->rules([
                            fn () => Rule::unique(OrganizationIntegration::class, 'name')
                                ->where('organization_id', app(OrganizationContext::class)->require()->getKey())
                                ->where('provider', 'maracuja_cms'),
                        ]),
                ])
                ->action(function (array $data): void {
                    Gate::authorize('create', OrganizationIntegration::class);
                    $issued = app(OrganizationIntegrationManager::class)
                        ->createApiToken('maracuja_cms', $data['name'], auth()->user());
                    $token = $issued['token'];

                    $this->js('navigator.clipboard.writeText('.Js::from($token).')');

                    Notification::make()
                        ->title('Canal créé — jeton copié')
                        ->body("Conservez-le maintenant, il ne sera plus affiché : {$token}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
