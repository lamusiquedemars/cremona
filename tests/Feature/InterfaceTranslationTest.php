<?php

namespace Tests\Feature;

use App\Services\OrganizationPresentation;
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
            $this->assertSame('Despesa registrada', __('cremona.dashboard.recorded_spend'));
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
}
