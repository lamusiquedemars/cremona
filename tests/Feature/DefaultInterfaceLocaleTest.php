<?php

namespace Tests\Feature;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\BrevoConnections\BrevoConnectionResource;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\ContempoProjections\ContempoProjectionResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Filament\Resources\EmailMailboxes\EmailMailboxResource;
use App\Filament\Resources\GoogleAdsConnections\GoogleAdsConnectionResource;
use App\Filament\Resources\InboundChannels\InboundChannelResource;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Filament\Resources\InstrumentAssets\InstrumentAssetResource;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\People\PersonResource;
use App\Filament\Resources\PrivateDocuments\PrivateDocumentResource;
use App\Filament\Resources\QuoteLineTemplates\QuoteLineTemplateResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\ServiceDefinitions\ServiceDefinitionResource;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use Tests\TestCase;

class DefaultInterfaceLocaleTest extends TestCase
{
    public function test_the_default_interface_locale_is_french(): void
    {
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('fr', config('app.fallback_locale'));
    }

    public function test_filament_generic_actions_are_translated_in_french(): void
    {
        $this->assertSame('Voir', __('filament-actions::view.single.label'));
        $this->assertSame('Modifier', __('filament-actions::edit.single.label'));
    }

    public function test_every_filament_resource_uses_a_french_model_label(): void
    {
        $labels = [
            AppointmentResource::class => ['rendez-vous', 'rendez-vous'],
            BrevoConnectionResource::class => ['connexion Brevo', 'connexions Brevo'],
            CampaignResource::class => ['campagne', 'campagnes'],
            CompanyResource::class => ['entreprise', 'entreprises'],
            ContempoProjectionResource::class => ['connecteur de publication', 'connecteurs de publication'],
            ConversationResource::class => ['conversation', 'correspondances'],
            CrmTaskResource::class => ['tâche', 'tâches'],
            EmailMailboxResource::class => ['boîte e-mail', 'boîtes e-mail'],
            GoogleAdsConnectionResource::class => ['compte Google Ads', 'comptes Google Ads'],
            InboundChannelResource::class => ['canal entrant', 'canaux entrants'],
            IncomingRequestResource::class => ['demande', 'demandes'],
            InstrumentAssetResource::class => ['instrument', 'instruments'],
            OrganizationResource::class => ['organisation', 'organisations'],
            PersonResource::class => ['contact', 'contacts'],
            PrivateDocumentResource::class => ['document privé', 'documents privés'],
            QuoteLineTemplateResource::class => ['ligne de devis enregistrée', 'lignes de devis enregistrées'],
            QuoteResource::class => ['devis', 'devis'],
            RentalResource::class => ['location', 'locations'],
            ServiceDefinitionResource::class => ['prestation atelier', 'prestations atelier'],
            StockItemResource::class => ['article de stock', 'articles de stock'],
            UserResource::class => ['utilisateur', 'utilisateurs'],
            WorkshopOrderResource::class => ['dossier atelier', 'dossiers atelier'],
        ];

        foreach ($labels as $resource => [$modelLabel, $pluralModelLabel]) {
            $this->assertSame($modelLabel, $resource::getModelLabel());
            $this->assertSame($pluralModelLabel, $resource::getPluralModelLabel());
        }
    }
}
