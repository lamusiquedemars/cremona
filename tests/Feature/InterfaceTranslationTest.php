<?php

namespace Tests\Feature;

use App\Services\OrganizationPresentation;
use App\Models\Organization;
use App\Tenancy\OrganizationContext;
use Tests\TestCase;

class InterfaceTranslationTest extends TestCase
{
    public function test_navigation_and_dashboard_catalogues_are_available_in_french_and_brazilian_portuguese(): void
    {
        try {
            app()->setLocale('fr');

            $this->assertSame('Contacts', __('cremona.navigation.items.contacts'));
            $this->assertSame('Priorités du jour', __('cremona.dashboard.priorities'));

            app()->setLocale('pt_BR');

            $this->assertSame('Contatos', __('cremona.navigation.items.contacts'));
            $this->assertSame('Prioridades de hoje', __('cremona.dashboard.priorities'));
            $this->assertSame('Campanhas ativas', __('cremona.dashboard.active_campaigns'));
            $this->assertSame('Despesas', __('cremona.dashboard.recorded_spend'));
            $this->assertSame('Olá', __('cremona.dashboard.greeting'));
            $this->assertSame('Visão geral', __('cremona.dashboard.overview'));
            $this->assertSame('Relacionamento com clientes', __('cremona.dashboard.client_follow_up'));
            $this->assertSame('Agendamentos', __('cremona.dashboard.appointments'));
            $this->assertSame('1 ativa', trans_choice('cremona.dashboard.active_campaigns_count', 1, ['count' => 1]));
            $this->assertSame('atualizada há 6 minutos', __('cremona.dashboard.sync_updated', ['date' => 'há 6 minutos']));
            $this->assertSame('Nenhuma ação urgente hoje.', __('cremona.dashboard.no_urgent_actions'));
            $this->assertSame('Em veiculação', __('cremona.dashboard.campaign_status.serving'));
            $this->assertSame('Solicitações', __('cremona.dashboard.requests'));
            $this->assertSame('Impressões', __('cremona.dashboard.impressions'));
            $this->assertSame('Cliques', __('cremona.dashboard.clicks'));
            $this->assertSame('0 solicitações desta campanha', trans_choice('cremona.dashboard.requests_from_campaign', 0, ['count' => 0]));
            $this->assertSame('Responder', __('cremona.crm.reply'));
            $this->assertSame('Histórico (2 mensagens)', trans_choice('cremona.crm.history', 2, ['count' => 2]));
            $this->assertSame('Em 14/09/2026 10:00, Marcos escreveu:', __('cremona.crm.wrote_on', ['date' => '14/09/2026 10:00', 'author' => 'Marcos']));
            $this->assertSame('Alterar status', __('cremona.request.change_status'));
            $this->assertSame('Criar ou vincular contato', __('cremona.request.create_or_link_contact'));
            $this->assertSame('2 possíveis correspondências encontradas. Verifique antes de criar um novo cadastro.', trans_choice('cremona.request.possible_matches', 2, ['count' => 2]));
            $this->assertSame('Aquisição', __('cremona.request.acquisition'));
            $this->assertSame('Histórico', __('cremona.request.history'));
        } finally {
            app()->setLocale('fr');
        }
    }

    public function test_organization_navigation_uses_the_current_interface_locale_when_it_is_not_customized(): void
    {
        try {
            app()->setLocale('pt_BR');

            $presentation = app(OrganizationPresentation::class);

            $this->assertSame('Relacionamento com clientes', $presentation->navigationGroupLabel('customer_follow_up', 'Suivi client'));
            $this->assertSame('Contatos', $presentation->navigationLabel('contacts', 'Contacts'));
        } finally {
            app()->setLocale('fr');
        }
    }

    public function test_brazilian_portuguese_ignores_french_presentation_overrides(): void
    {
        $organization = new Organization([
            'settings' => [
                'presentation' => [
                    'labels' => [
                        'customer_follow_up' => 'Suivi client',
                        'contacts' => 'Contacts',
                        'requests' => 'Demandes',
                        'campaigns' => 'Campagnes',
                    ],
                ],
            ],
        ]);

        try {
            app()->setLocale('pt_BR');

            app(OrganizationContext::class)->run($organization, function (): void {
                $presentation = app(OrganizationPresentation::class);

                $this->assertSame('Relacionamento com clientes', $presentation->navigationGroupLabel('customer_follow_up', 'Suivi client'));
                $this->assertSame('Contatos', $presentation->navigationLabel('contacts', 'Contacts'));
                $this->assertSame('Solicitações', $presentation->navigationLabel('requests', 'Demandes'));
                $this->assertSame('Campanhas', $presentation->navigationLabel('campaigns', 'Campagnes'));
            });
        } finally {
            app()->setLocale('fr');
        }
    }
}
